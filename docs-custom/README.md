# docs-custom

`docs-custom/` is the only home for project-specific implementation, deployment,
and maintenance documents in this workspace.

## Layout

- `plans/`: implementation plans and architecture decisions
- `guides/`: deployment, onboarding, and operating guides
- `templates/`: reusable report and checklist templates
- `reports/`: generated or maintained audit and rollout reports

## Current documents

- `plans/LIBRENMS_MULTIVENDOR_HUAWEI_PLAN_CN.md`
- `guides/HUAWEI_MIB_DEPLOYMENT_CN.md`
- `guides/DOCKER_DEPLOYMENT_CN.md`
- `guides/DEVICE_ONBOARDING_CN.md`
- `templates/HUAWEI_MIB_DIFF_TEMPLATE.md`

## Rules

- Do not add project-specific Markdown docs to the repository root.
- Do not put implementation notes into `mib-archives/`.
- Keep upstream LibreNMS `doc/` unchanged unless contributing upstream.
