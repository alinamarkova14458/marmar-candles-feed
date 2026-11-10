# Планировщик Instagram MarMar (бесплатный, замена Metricool)

Workflow `.github/workflows/instagram-scheduler.yml` запускается по расписанию (GitHub
часто запускает его с опозданием на несколько часов). Он читает `queue/*.json` и публикует
посты, время которых пришло, через бесплатный Instagram API. `published.json` — журнал, его
пишет сам workflow, руками не править.

## Подтверждение Алины
Посты попадают в `queue/` на `master` **только через запрос на слияние**. Claude готовит
посты (тексты, фото, даты) в запросе на слияние, Алина смотрит и нажимает Merge — это и есть
её «да». Без слияния ничего не публикуется.

## Формат поста
`queue/<YYYY-MM-DD>-<тема>.json`, список постов:

```json
[{"date": "2026-11-20T18:00:00", "type": "image",
  "media": ["https://raw.githubusercontent.com/alinamarkova14458/marmar-candles-feed/master/social/instagram/media/2026-11-20-svecites.jpg"],
  "caption": "Sveiki! Я сделала новые свечи-цифры 🎂 #marmarcandles #tortesveces"}]
```

- `date` без смещения = время Риги.
- `type`: `image` (1 фото), `carousel` (2–20 фото), `reel` (1 видео MP4/MOV).
- `media`: публичные https-ссылки. Фото только JPEG, до 8 МБ. Файлы кладём в `media/`
  (ссылка raw.githubusercontent.com, как у фида).
- Встроенные проверки: максимум 5 хэштегов, подпись до 2200 знаков. Пост с ошибкой
  пропускается и записывается в журнал. Пост, опоздавший больше чем на 24 часа, пропускается.
- Если публикация упала, запуск workflow красный, GitHub присылает письмо. Повторно такой пост
  сам не публикуется (чтобы не было дублей): нужно поменять ему `id` или дату.

## Настройка (один раз, делает Алина) — сделано 10.11.2026
Маркер системного пользователя из настроек компании Meta, бессрочный, без URL и кодов.

1. Instagram @marmar.candles — профессиональный аккаунт, связан со страницей Facebook MarMar Candles.
2. developers.facebook.com: приложение «MarMar Scheduler» (сценарий «Управление сообщениями
   и контентом в Instagram», компания Marmar Candles).
3. business.facebook.com/settings → «Пользователи» → «Системные пользователи» →
   `marmar-scheduler` (админ). «Назначить объекты»: страница MarMar Candles, Instagram
   marmar.candles, приложение MarMar Scheduler («Управление приложением»).
4. «Сгенерировать маркер»: приложение MarMar Scheduler, срок «Никогда», разрешения
   `instagram_basic`, `instagram_content_publish` (+ `pages_show_list`, если предлагается).
5. GitHub → Settings → Secrets and variables → Actions → секрет `IG_ACCESS_TOKEN`.
6. Проверка: Actions → «Instagram scheduler» → Run workflow с галочкой dry_run, в логе
   `token OK: @marmar.candles`.

Запасной путь (если маркер из настроек компании перестанет подходить): конфигурация
«API setup with Unified login», URL переадресации `https://marmarcandles.com/lv/`,
секреты `META_APP_ID` и `META_APP_SECRET`, разовый workflow «Instagram connect (one-time)».

Пока секрета нет, workflow только пишет в лог, что опубликовал бы.
