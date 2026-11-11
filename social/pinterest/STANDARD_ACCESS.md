# Заявка на Pinterest Standard access (App ID 1636006)

## Сценарий видео (1–2 минуты, без звука)

Запись экрана на Windows 11: **Win + Shift + R** (Ножницы → запись видео), выделите окно браузера.
Перед записью закройте лишние вкладки, App secret на экране не открывайте.

1. **Приложение.** developers.pinterest.com → My apps → `jalina.art Pin Scheduler` (видны название и App ID). 3–5 секунд.
2. **Что оно делает.** GitHub → `marmar-candles-feed` → `social/pinterest/README.md`. Медленно прокрутите начало. 5 секунд.
3. **Вход через OAuth.** Откройте ссылку подключения:
   `https://www.pinterest.com/oauth/?client_id=1636006&redirect_uri=https%3A%2F%2Fjalina.art%2F&response_type=code&scope=boards:read,boards:write,pins:read,pins:write,user_accounts:read`
   Задержитесь на экране согласия Pinterest (виден список разрешений) → **Allow**.
4. **Возврат на сайт.** Откроется `https://jalina.art/?code=...`. Покажите адресную строку 2 секунды, скопируйте код.
5. **Сохранение ключа.** GitHub → Actions → **Pinterest connect (one-time)** → Run workflow → вставьте код → Run.
   Дождитесь зелёной галочки, откройте шаг и покажите строку `Connected (...). PINTEREST_REFRESH_TOKEN saved.` (сам ключ скрыт звёздочками).
6. **Очередь пинов.** GitHub → `social/pinterest/queue/` → откройте файл с пином (дата, доска, заголовок, картинка, ссылка).
7. **Публикация.** Actions → **Pinterest scheduler** → Run workflow (dry_run снят) → откройте журнал: строка `OK ... -> pin <id>`.
   Затем откройте этот пин в Pinterest. (Пункт 7 снимаем, когда Claude подтвердит, что тестовый пин проходит.)

Сохраните видео и загрузите на YouTube как **Unlisted** (доступ по ссылке) или на Google Drive с доступом «у кого есть ссылка».

## Текст заявки (вставлять по-английски)

**App description / use case**

> jalina.art Pin Scheduler is a private, single-user tool for my own Pinterest business account (jalinaart). I am an independent designer from Riga, Latvia; I write a craft blog (jalina.art) and sell my own clipart sets. I create the Pin images and texts myself, and this tool publishes them on a schedule (about 10–15 Pins per day) to my own boards, with links to my blog articles and shop pages.
>
> How it works: I authorize the app once through the standard Pinterest OAuth flow (redirect URI https://jalina.art/). The refresh token is stored only as an encrypted GitHub Actions secret and is never logged. A scheduled GitHub Actions job reads my Pin queue (title, description, image URL on jalina.art, destination link, board) and creates the Pins with POST /v5/pins. It reads only my own boards (boards:read) and creates boards/Pins only in my account.
>
> It does not access other users' data, does not scrape Pinterest, does not store passwords or cookies, and does not run ads. There are no other users. Privacy policy: https://jalina.art/privacy/

**Scopes requested:** `pins:write`, `pins:read`, `boards:read`, `boards:write`, `user_accounts:read`.

**Video:** ссылка на видео из сценария выше.
