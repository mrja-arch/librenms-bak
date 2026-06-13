<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LibreNMS\Interfaces\ValidationFixer;
use LibreNMS\Validator;

class ValidateController extends Controller
{
    public function index(): View
    {
        $validationGroups = (new Validator())->getValidationGroups();

        $groups = collect($validationGroups)
            ->map(fn (bool $enabled, string $group) => [
                'group' => $group,
                'enabled' => $enabled,
                'name' => trans("validation.validations.groups.{$group}"),
            ])
            ->values()
            ->all();

        return view('validate.index', [
            'groups' => $groups,
        ]);
    }

    public function runValidation(?string $group = null): JsonResponse
    {
        $validator = new Validator();
        $validator->validate($group ? [$group] : []);

        $results = $validator->toArray();
        array_walk_recursive($results, function (&$value): void {
            if (! is_string($value)) {
                return;
            }

            $value = str_replace(
                [
                    'https://community.librenms.org/t/new-default-database-charset-collation/14956',
                    'https://docs.librenms.org/Installation/Install-LibreNMS/#configure-php-fpm',
                ],
                [
                    route('help.topic', 'database') . '#字符集与排序规则',
                    route('help.public') . '#文件权限或空白页面',
                ],
                $value
            );
        });

        return response()->json($results);
    }

    public function runFixer(Request $request): JsonResponse
    {
        $this->validate($request, [
            'fixer' => [
                'starts_with:LibreNMS\Validations',
                function ($attribute, $value, $fail): void {
                    if (! class_exists($value) || ! in_array(ValidationFixer::class, class_implements($value))) {
                        $fail(trans('validation.results.invalid_fixer'));
                    }
                },
            ],
        ]);
        $fixer = $request->input('fixer');

        return response()->json([
            'result' => (new $fixer)->fix(),
        ]);
    }
}
