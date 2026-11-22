# You Emulator

Плагин WordPress, который превращает страницу сайта в закрытую библиотеку ретро-игр, запускаемых прямо в браузере на базе [EmulatorJS](https://github.com/Serge-Nook/EmulatorJS) 4.2.3.

*A WordPress plugin that turns a page into a private retro game library played in the browser, powered by EmulatorJS. English summary below.*

- **Системы:** NES, SNES, Sega Genesis / Mega Drive, Game Boy Advance, PlayStation, PSP, Nintendo 64.
- **Доступ:** гости видят только баннер. Авторизованные пользователи видят публичные игры. Приватные игры открываются выбранным пользователям и ролям.
- **Сохранения:** 4 слота на игру (0 — быстрый), со скриншотами, хранятся на сервере и привязаны к профилю.
- **Управление:** Shift+1 / Shift+2 / Shift+3 — быстрое сохранение / загрузка / полный экран. На геймпаде LB+RB+X / LB+RB+B / LB+RB+Y (комбинации настраиваются).
- **Темы:** Neon, Retro-cream, Dark, CRT, все анимированные и с учётом `prefers-reduced-motion`. Игрок выбирает тему сам.
- **Языки:** русский, английский, китайский.

## Требования

- WordPress 6.5+, PHP 8.1+.
- HTTPS. Без него браузер не даёт WASM-потоки и Gamepad API.
- Для PSP включите в настройках cross-origin isolation (заголовки COOP/COEP). Ядру PPSSPP нужен `SharedArrayBuffer`.
- BIOS для PS1 (ядро `mednafen_psx_hw`). Ядро `pcsx_rearmed` запускается и без BIOS, на встроенном HLE BIOS.

## Установка

1. Скачайте `you-emulator-<версия>.zip` со страницы [Releases](https://github.com/Serge-Nook/you-emulator/releases). В архив уже входят EmulatorJS и ядра.
2. В админке откройте «Плагины → Добавить новый → Загрузить плагин».
3. Активируйте плагин. Он создаст защищённую папку `wp-content/uploads/you-emulator/` и таблицу сохранений.
4. «Игры → BIOS»: загрузите BIOS для PS1 (и при желании для GBA).
5. «Игры → Добавить игру»: заполните название, консоль, ROM, обложку, жанры и доступ.
6. Добавьте на страницу шорткод `[youem_library]` или блок «Библиотека ретро-игр».

Параметры шорткода: `console="snes"`, `per_page="24"`.

## Форматы ROM

| Система | Ядра | Файлы |
| --- | --- | --- |
| NES | fceumm, nestopia | .nes .fds .unf .unif |
| SNES | snes9x | .smc .sfc .fig .swc |
| Genesis | genesis_plus_gx, picodrive | .md .gen .smd .bin |
| GBA | mgba | .gba .agb |
| PlayStation | mednafen_psx_hw, pcsx_rearmed | .cue + .bin, .pbp, .chd, .m3u, .exe |
| PSP | ppsspp | .iso .cso .pbp .chd |
| N64 | mupen64plus_next, parallel_n64 | .z64 .n64 .v64 |

Для всех систем также подходят .zip и .7z. Для PS1 выберите сразу `.cue` и все его `.bin`: плагин проверит ссылки в cue-листе и упакует диск в один архив. Расширения и сигнатуры файлов проверяются при загрузке. Большие файлы (до 4 ГБ) загружаются частями с докачкой.

## Безопасность

- ROM, BIOS и сохранения лежат в `wp-content/uploads/you-emulator/`. Папка закрыта для прямого доступа (`.htaccess`, `web.config`, `index.php`). На nginx добавьте:
  ```nginx
  location ^~ /wp-content/uploads/you-emulator/ { deny all; }
  ```
- Файлы отдаются только через REST (`/wp-json/youem/v1/…`) после проверки доступа к игре. Поддерживается HTTP Range.
- Все изменяющие запросы требуют nonce (`X-WP-Nonce`) и проверяют права (`youem_play`, `youem_manage_games`, мета-право `youem_play_game`).
- Есть ограничения: квота сохранений на пользователя, минимальный интервал между сохранениями и лимит открытых игр за 15 минут.

## Обложки и метаданные

Обложку можно загрузить вручную или заполнить из [TheGamesDB](https://thegamesdb.net/) или [ScreenScraper](https://www.screenscraper.fr/). Для этого укажите ключи API в «Игры → Настройки». Автозаполнение подставляет обложку, год, разработчика, жанры и описание.

## REST API

| Метод | Путь | Назначение |
| --- | --- | --- |
| GET | `/games` | Список доступных игр (`console`, `genre`, `search`, `page`, `per_page`) |
| GET | `/games/{id}` | Данные игры |
| GET, HEAD | `/games/{id}/rom/{name}` | ROM (Range) |
| GET, HEAD | `/games/{id}/bios/bios.zip` | BIOS системы |
| GET | `/games/{id}/saves` | Слоты пользователя |
| POST | `/games/{id}/save` | Сохранить состояние в слот 0–3 |
| GET | `/games/{id}/load?slot=N` | Загрузить состояние |
| DELETE | `/games/{id}/save?slot=N` | Удалить слот |
| GET | `/games/{id}/saves/{slot}/screenshot` | Скриншот слота |
| GET | `/config` | Настройки плеера |
| POST | `/me/theme` | Тема пользователя |
| POST | `/admin/upload-chunk`, `/admin/assemble` | Загрузка ROM частями |
| GET | `/admin/upload-status`, `/admin/users` | Статус загрузки, поиск пользователей |
| POST | `/admin/scrape` | Автозаполнение метаданных |

## Хуки

`youem_consoles`, `youem_can_access_game`, `youem_validate_rom`, `youem_max_rom_size`.

## Разработка

```bash
composer install            # PHPUnit, WP_Mock, PHPCS (WordPress Coding Standards)
composer test               # модульные тесты
composer lint               # phpcs
bin/fetch-emulatorjs.sh     # EmulatorJS 4.2.3 и ядра в vendor/emulatorjs/data
bin/build-zip.sh            # build/you-emulator-<версия>.zip
```

Ядра (`*.data`) не хранятся в git. Их скачивает `bin/fetch-emulatorjs.sh` с `cdn.emulatorjs.org`, а в релизный ZIP они попадают уже скачанными. Параметр `--from-fork DIR` берёт runtime из собранного форка EmulatorJS.

## English

You Emulator turns a WordPress page into a private, login-only retro game library: NES, SNES, Genesis, GBA, PS1, PSP and N64 in the browser via EmulatorJS 4.2.3.

- ROMs, BIOS and saves are stored in a protected folder and served only through authenticated REST endpoints, with Range support.
- Each game has four server-side save slots per user.
- Keyboard and gamepad shortcuts are included, plus four animated themes and per-game user/role restrictions.
- Covers come from manual upload or TheGamesDB/ScreenScraper.

Install the release ZIP, upload BIOS files, add games and place `[youem_library]` on a page. HTTPS is required; PSP also needs the COOP/COEP option.

## Лицензия

GPL-3.0-or-later, см. [LICENSE](LICENSE).

- Эмулятор: [EmulatorJS](https://github.com/EmulatorJS/EmulatorJS) (форк [Serge-Nook/EmulatorJS](https://github.com/Serge-Nook/EmulatorJS)), GPL-3.0.
- Ядра: проекты [libretro](https://www.libretro.com/), каждое под своей лицензией. Отчёты по ним лежат в `vendor/emulatorjs/data/cores/reports/`.

Загружайте только игры и BIOS, на использование которых у вас есть права.

Автор: Горшков Сергей Владимирович, <https://nookbat.ru>.
