<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LocalHelpController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->input('q'));
        $topics = collect(config('local-help.topics'))
            ->map(fn (array $topic, string $slug) => $this->topicMetadata($slug, $topic));

        if ($query !== '') {
            $needle = Str::lower($query);
            $topics = $topics->filter(function (array $topic) use ($needle): bool {
                $body = $this->readTopic($topic);

                return Str::contains(Str::lower($topic['title'] . "\n" . $topic['summary'] . "\n" . $body), $needle);
            });
        }

        return view('help.index', [
            'pagetitle' => __('help.title'),
            'query' => $query,
            'topics' => $topics,
        ]);
    }

    public function show(string $topic): View
    {
        return $this->renderTopic($topic, false);
    }

    public function publicSupport(): View
    {
        return $this->renderTopic('support', true);
    }

    private function renderTopic(string $slug, bool $publicOnly): View
    {
        $topics = config('local-help.topics');
        $topic = $topics[$slug] ?? null;

        if (! is_array($topic) || ($publicOnly && empty($topic['public']))) {
            throw new NotFoundHttpException;
        }

        $metadata = $this->topicMetadata($slug, $topic);
        $html = Str::markdown($this->readTopic($metadata), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return view($publicOnly ? 'help.public' : 'help.show', [
            'pagetitle' => $metadata['title'],
            'topic' => $metadata,
            'html' => $html,
        ]);
    }

    private function topicMetadata(string $slug, array $topic): array
    {
        return [
            'slug' => $slug,
            'title' => $topic['title'],
            'summary' => $topic['summary'],
            'file' => base_path($topic['file']),
            'public' => (bool) ($topic['public'] ?? false),
        ];
    }

    private function readTopic(array $topic): string
    {
        $contents = @file_get_contents($topic['file']);

        if ($contents === false) {
            report("Local help topic is missing: {$topic['file']}");

            return '# 文档暂不可用' . PHP_EOL . PHP_EOL . '本地文档文件不存在，请联系系统管理员。';
        }

        return $contents;
    }
}
