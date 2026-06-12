from pathlib import Path


ROOT = Path("/opt/librenms")


def replace(relative_path: str, old: str, new: str) -> None:
    path = ROOT / relative_path
    content = path.read_text()
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f"{relative_path}: expected one match, found {count}: {old!r}")

    path.write_text(content.replace(old, new))


replace(
    "resources/views/layouts/menu.blade.php",
    "aria-hidden=\"true\"></i> NAC</a></li>",
    "aria-hidden=\"true\"></i> {{ __('NAC') }}</a></li>",
)
replace(
    "resources/views/layouts/menu.blade.php",
    "{{ $routing_menu_entry['text'] }}</a></li>",
    "{{ __($routing_menu_entry['text']) }}</a></li>",
)
replace(
    "resources/views/layouts/menu.blade.php",
    "header: '<h5><strong>&nbsp;Devices</strong></h5>',",
    "header: '<h5><strong>&nbsp;{{ __('Devices') }}</strong></h5>',",
)
replace(
    "resources/views/layouts/menu.blade.php",
    "header: '<h5><strong>&nbsp;Ports</strong></h5>',",
    "header: '<h5><strong>&nbsp;{{ __('Ports') }}</strong></h5>',",
)
replace(
    "resources/views/layouts/menu.blade.php",
    "header: '<h5><strong>&nbsp;BGP Sessions</strong></h5>',",
    "header: '<h5><strong>&nbsp;{{ __('BGP Sessions') }}</strong></h5>',",
)
replace(
    "resources/views/layouts/menu.blade.php",
    """                        <li><a href="{{ route('preferences.index') }}"><i class="fa fa-cog fa-fw fa-lg"
                                                                  aria-hidden="true"></i> {{ __('My Settings') }}</a></li>
                        <li><x-theme-toggle /></li>""",
    """                        <li><a href="{{ route('preferences.index') }}"><i class="fa fa-cog fa-fw fa-lg"
                                                                  aria-hidden="true"></i> {{ __('My Settings') }}</a></li>
                        <li class="dropdown-submenu">
                            <a href="#"><i class="fa fa-language fa-fw fa-lg" aria-hidden="true"></i> {{ __('Language') }}</a>
                            <ul class="dropdown-menu">
                                <li><a href="#" onclick="setInterfaceLocale('en'); return false;"><i class="fa fa-fw @if(app()->getLocale() === 'en') fa-check @endif" aria-hidden="true"></i> English</a></li>
                                <li><a href="#" onclick="setInterfaceLocale('zh-CN'); return false;"><i class="fa fa-fw @if(app()->getLocale() === 'zh-CN') fa-check @endif" aria-hidden="true"></i> 简体中文</a></li>
                            </ul>
                        </li>
                        <li><x-theme-toggle /></li>""",
)
replace(
    "resources/views/layouts/menu.blade.php",
    """<script>
    var devices = new Bloodhound({""",
    """<script>
    function setInterfaceLocale(locale) {
        $.ajax({
            url: '{{ route('preferences.store') }}',
            dataType: 'json',
            type: 'POST',
            data: {
                pref: 'locale',
                value: locale
            },
            success: function () {
                location.reload();
            }
        });
    }

    var devices = new Bloodhound({""",
)
replace(
    "resources/views/auth/2fa.blade.php",
    ">Manual</button>",
    ">{{ __('Manual') }}</button>",
)
replace(
    "resources/views/auth/2fa.blade.php",
    ">QR</button>",
    ">{{ __('QR') }}</button>",
)
replace(
    "resources/views/device/tabs/notes.blade.php",
    '<i class="fa fa-check"></i> Save</button>',
    '<i class="fa fa-check"></i> {{ __(\'Save\') }}</button>',
)
replace(
    "resources/views/poller/log.blade.php",
    ">All devices</a> \"+",
    ">{{ __('All devices') }}</a> \"+",
)
replace(
    "resources/views/poller/log.blade.php",
    ">Unpolled devices</a>\"+",
    ">{{ __('Unpolled devices') }}</a>\"+",
)
replace(
    "resources/views/device/tabs/logs/eventlog.blade.php",
    '>Filter</button>\' +',
    '>{{ __(\'Filter\') }}</button>\' +',
)
replace(
    "resources/views/device/tabs/logs/graylog.blade.php",
    '>Filter</button>&nbsp;"+',
    '>{{ __(\'Filter\') }}</button>&nbsp;"+',
)
replace(
    "resources/views/device/tabs/logs/syslog.blade.php",
    '>Filter</button>\' +',
    '>{{ __(\'Filter\') }}</button>\' +',
)
