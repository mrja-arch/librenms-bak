<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DiagnosticBundle;
use Illuminate\Support\Facades\File;
use phpseclib3\Net\SSH2;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class DiagnosticBundleService
{
    private const HUAWEI_COMMANDS = [
        'display version',
        'display current-configuration configuration snmp',
        'display snmp-agent trap feature-name ifnet all',
        'display lldp neighbor brief',
        'display interface brief',
    ];

    public function build(DiagnosticBundle $bundle): void
    {
        $bundle->update(['status' => 'running', 'started_at' => now(), 'error' => null]);
        $archiveDir = '/data/diagnostics';
        $workDir = "$archiveDir/work-{$bundle->id}";
        File::ensureDirectoryExists($workDir, 0750);
        File::ensureDirectoryExists($archiveDir, 0750);

        try {
            $this->write($workDir, 'manifest.txt', $this->manifest($bundle));
            $this->write($workDir, 'system.txt', $this->systemDiagnostics());
            $this->collectLogs($workDir);

            if ($bundle->device_id) {
                $device = Device::findOrFail($bundle->device_id);
                $this->write($workDir, 'device.txt', $this->deviceDiagnostics($device));
            }

            $filename = sprintf('librenms-diagnostics-%d-%s.zip', $bundle->id, now()->format('Ymd-His'));
            $path = $archiveDir . DIRECTORY_SEPARATOR . $filename;
            $this->zipDirectory($workDir, $path);
            $bundle->update([
                'status' => 'ready',
                'path' => $path,
                'filename' => $filename,
                'size' => filesize($path),
                'sha256' => hash_file('sha256', $path),
                'completed_at' => now(),
                'expires_at' => now()->addDays(3),
            ]);
        } catch (\Throwable $e) {
            $bundle->update([
                'status' => 'failed',
                'error' => $this->redact($e->getMessage()),
                'completed_at' => now(),
                'expires_at' => now()->addDays(3),
            ]);
            throw $e;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    private function manifest(DiagnosticBundle $bundle): string
    {
        return implode(PHP_EOL, [
            'LibreNMS diagnostic bundle',
            'Bundle ID: ' . $bundle->id,
            'Created: ' . now()->toIso8601String(),
            'Device ID: ' . ($bundle->device_id ?: 'system only'),
            'Retention: 3 days',
            'Commands are selected from a fixed read-only allowlist.',
        ]) . PHP_EOL;
    }

    private function systemDiagnostics(): string
    {
        $commands = [
            'php-version' => [PHP_BINARY, '--version'],
            'librenms-validation' => [PHP_BINARY, base_path('validate.php')],
            'schedule' => [PHP_BINARY, base_path('artisan'), 'schedule:list'],
            'queue-summary' => [PHP_BINARY, base_path('artisan'), 'queue:monitor', 'operations,default'],
            'disk' => ['df', '-h', '/data'],
            'memory' => ['sh', '-lc', 'cat /proc/meminfo | head -n 20'],
            'cpu' => ['sh', '-lc', 'cat /proc/cpuinfo | grep -c "^processor"'],
            'huawei-mib-check' => ['snmptranslate', '-M', '+' . base_path('mibs/huawei'), '-m', 'HUAWEI-IF-EXT-MIB', 'HUAWEI-IF-EXT-MIB::hwPhysicalAdminIfDown'],
        ];
        $output = '';
        foreach ($commands as $name => $command) {
            $output .= "\n===== $name =====\n" . $this->run($command);
        }

        return $this->redact($output);
    }

    private function collectLogs(string $workDir): void
    {
        $logDir = '/data/logs';

        foreach (glob($logDir . '/*.log') ?: [] as $log) {
            $process = new Process(['tail', '-n', '2000', $log]);
            $process->setTimeout(30)->run();
            $this->write($workDir, 'logs/' . basename($log), $this->redact($process->getOutput() . $process->getErrorOutput()));
        }
    }

    private function deviceDiagnostics(Device $device): string
    {
        $username = (string) env('DIAGNOSTIC_SSH_USERNAME', env('OXIDIZED_USERNAME', ''));
        $password = (string) env('DIAGNOSTIC_SSH_PASSWORD', env('OXIDIZED_PASSWORD', ''));
        if ($username === '' || $password === '') {
            throw new RuntimeException('Diagnostic SSH credentials are not configured.');
        }

        $sshPort = (int) ($device->getAttrib('override_device_ssh_port') ?: 22);
        $ssh = new SSH2($device->hostname, $sshPort, 20);
        if (! $ssh->login($username, $password)) {
            throw new RuntimeException("SSH login failed for {$device->hostname}.");
        }

        $ssh->setTimeout(15);
        $this->readHuaweiOutput($ssh);
        $ssh->write("screen-length 0 temporary\n");
        $this->readHuaweiOutput($ssh);
        $output = "Device: {$device->displayName()} ({$device->hostname})\n";
        foreach (self::HUAWEI_COMMANDS as $command) {
            $ssh->write($command . "\n");
            $result = $this->readHuaweiOutput($ssh);
            $output .= "\n===== $command =====\n" . ($result ?: '[no output]');
        }

        return $this->redact($output);
    }

    private function readHuaweiOutput(SSH2 $ssh): string
    {
        $output = '';
        for ($page = 0; $page < 100; $page++) {
            $chunk = (string) $ssh->read('/(?:(?:<[^>]+>|\[[^\]]+\])\s*$|---- More ----)/', SSH2::READ_REGEX);
            $output .= $chunk;
            if (! str_contains($chunk, '---- More ----')) {
                break;
            }
            $ssh->write(' ');
        }

        return preg_replace('/(?:\x08+|\s*---- More ----\s*)/', '', $output) ?? $output;
    }

    private function run(array $command): string
    {
        $process = new Process($command, base_path());
        $process->setTimeout(120)->run();

        return sprintf(
            "Exit code: %d\n%s%s",
            $process->getExitCode(),
            $process->getOutput(),
            $process->getErrorOutput()
        );
    }

    private function zipDirectory(string $source, string $destination): void
    {
        $zip = new ZipArchive;
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create diagnostic archive.');
        }

        foreach (File::allFiles($source) as $file) {
            $zip->addFile($file->getPathname(), $file->getRelativePathname());
        }
        $zip->close();
    }

    private function write(string $directory, string $relativePath, string $contents): void
    {
        $path = $directory . DIRECTORY_SEPARATOR . $relativePath;
        File::ensureDirectoryExists(dirname($path), 0750);
        File::put($path, $contents);
    }

    private function redact(string $value): string
    {
        $secrets = array_filter([
            env('DB_PASSWORD'),
            env('SNMP_TRAP_COMMUNITY'),
            env('DIAGNOSTIC_SSH_PASSWORD'),
            env('OXIDIZED_PASSWORD'),
            env('OXIDIZED_API_TOKEN'),
        ], fn ($secret) => is_string($secret) && strlen($secret) >= 3);

        foreach ($secrets as $secret) {
            $quoted = preg_quote($secret, '/');
            $pattern = ctype_alnum($secret)
                ? '/(?<![A-Za-z0-9])' . $quoted . '(?![A-Za-z0-9])/'
                : '/' . $quoted . '/';
            $value = preg_replace($pattern, '[REDACTED]', $value) ?? $value;
        }

        return preg_replace('/(\bcipher\s+)\S+/i', '$1[REDACTED]', $value) ?? $value;
    }
}
