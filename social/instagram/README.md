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

## Настройка (один раз, делает Алина, ~15 минут)
1. Instagram @marmar.candles должен быть **профессиональным** (Business или Creator).
2. developers.facebook.com → My Apps → Create App → тип **Business** → добавить продукт
   **Instagram** → «API setup with Instagram business login».
3. В разделе «Generate access tokens» добавить аккаунт marmar.candles и нажать
   **Generate token**, войти в Instagram, скопировать токен. Нужны разрешения
   `instagram_business_basic` и `instagram_business_content_publish`.
4. GitHub → Settings → Secrets and variables → Actions → New repository secret:
   `IG_ACCESS_TOKEN` = скопированный токен. (`GH_SECRETS_PAT` уже есть от Pinterest — он
   нужен, чтобы workflow раз в неделю сам продлевал токен; токен живёт 60 дней.)
5. Actions → Instagram scheduler → Run workflow с галочкой dry_run — проверить, что всё
   работает, ничего не публикуя.

Пока секрета нет, workflow только пишет в лог, что опубликовал бы.
