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

## Настройка (один раз, делает Алина)
Для новых приложений Meta вход через Instagram закрыт, используется «единый вход»
(unified login) с маркером системного пользователя, который не истекает.

1. Instagram @marmar.candles должен быть **профессиональным** (Бизнес или Автор).
2. developers.facebook.com → «Мои приложения» → «Создать приложение», сценарий
   «Управление сообщениями и контентом в Instagram», компания Marmar Candles.
3. «Сценарии использования» → Instagram API → «API setup with Unified login» →
   «Создать новую конфигурацию». Маркер: системного пользователя, срок: «Никогда»,
   объект: аккаунт Instagram marmar.candles.
4. Там же «URL переадресации»: `https://jalina.art/`.
5. «Настройки приложения» → «Основное»: «Идентификатор приложения» и «Секрет приложения»
   сохранить в GitHub → Settings → Secrets and variables → Actions как секреты
   `META_APP_ID` и `META_APP_SECRET`. (`GH_SECRETS_PAT` уже есть от Pinterest.)
6. Открыть «Login URL» конфигурации, войти, выбрать marmar.candles, разрешить. Браузер
   откроет jalina.art с `?code=...` в адресной строке. Скопировать значение после `code=`
   и в течение нескольких минут запустить Actions → «Instagram connect (one-time)» с ним.
   Workflow сам сохранит маркер в секрет `IG_ACCESS_TOKEN` и покажет подключённый аккаунт.
7. Actions → «Instagram scheduler» → Run workflow с галочкой dry_run: в логе должна быть
   строка `token OK: @marmar.candles`. Ничего не публикуется.

Пока секрета нет, workflow только пишет в лог, что опубликовал бы.
