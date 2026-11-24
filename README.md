# TravelX - страници на събитията

Изходният код на двете страници на TravelX на travelx.bg. Всяка е статична страница (един HTML файл), която се качва в WordPress като плъгин и се публикува като обикновена папка на сайта.

| Папка | Страница | Плъгин | Префикс | Локален преглед |
|---|---|---|---|---|
| `travelx-sliven/` | https://travelx.bg/sliven/ | `wordpress/travelx-sliven.php` | `txs_` | `node serve.mjs` → http://localhost:4321 |
| `travelx-plovdiv/` | https://travelx.bg/plovdiv/ (и `/plovdiv/bg/`) | `wordpress/travelx-plovdiv.php` | `txpv_` | `node serve.mjs` → http://localhost:4322 |

`travelx-home/` е отделен малък плъгин без страница: пренасочва https://travelx.bg/ (302) към текущото събитие (`TXH_CURRENT`, сега `plovdiv`), а адресите на минали събития (`TXH_RETIRED`, сега `sliven`) към него, след като плъгинът на миналото събитие бъде деактивиран. За следващото събитие: сменете `TXH_CURRENT`, увеличете `TXH_VERSION`, `python tools/build.py`, качете zip-а. Деактивирането на плъгина връща старата начална страница.

## Структура на всяка папка

- `index.html` - страницата (HTML, CSS и JS в един файл)
- `.htaccess` - кеширане и MIME типове за публикуваната папка
- `assets/` - генерираните снимки и шрифтове. Не се редактират на ръка.
- `_src/` - оригиналите на снимките и логата
- `tools/optimize.py` - прави `assets/` от `_src/` (AVIF/WebP)
- `tools/build.py` - прави `dist/travelx-<град>-<версия>.zip` за качване в WordPress
- `wordpress/travelx-<град>.php` - плъгинът. Версията е в константата `*_VERSION`.
- само Пловдив: `content/` (преводите в `bg.json`, програмата в `TRAVEL_X_2027_Program.xlsx`), `tools/i18n.py`, `tools/program.py`, генерираният `bg/index.html`

## Нужни инструменти

- Python 3 с Pillow 11.3 или по-нов (`pip install pillow`, AVIF е вграден), за Пловдив и `openpyxl`
- Node.js - само за локалния преглед

## Пускане на нова версия

1. Увеличете версията в `wordpress/travelx-<град>.php`.
2. `python tools/build.py` в папката на страницата.
3. WordPress: "Plugins" → "Add New" → "Upload Plugin" → zip-ът → "Replace current with uploaded".
4. Отворете "Tools" → "TravelX Sliven" / "TravelX Plovdiv" - зареждането публикува новата версия.

Zip-овете на версиите, които са качени в момента, са в [Releases](../../releases).

Пълното ръководство (визия, метод, плъгинът, адреси, поддръжка, проблеми и бележки за AI агенти) е в ClickUp: документ "TravelX уеб страници: изграждане и поддръжка".
