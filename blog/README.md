# Блог MarMar CANDLES — план и журнал статей

Статьи пишет и публикует Claude (разрешение Алины от 06.11.2026). Темы выбираются по данным:
Search Console (запросы из Латвии), заказы WooCommerce, существующие статьи (без каннибализации).

## Опубликовано

| Дата | Статья | Целевые запросы | Ведёт на | Проверить в GSC |
|---|---|---|---|---|
| 06.11.2026 | [Ziemassvētku dāvanas ar dvēseli: rokām darinātas sveces līdz 10 € un 20 €](https://marmarcandles.com/lv/ziemassvetku-davanas-sveces/) (`2026-11-ziemassvetku-davanas.html`) | ziemassvētku dāvanas, ziemassvētku sveces, dāvanas kolēģiem | Ziemassvētku sveces, Dāvanas | ~01.12.2026 |
| 07.11.2026 09:00 ⏰ | [Adventes un Ziemassvētku galda dekors: kā izvēlēties galda sveces](https://marmarcandles.com/lv/adventes-ziemassvetku-galda-sveces/) | galda sveces, adventes sveces | Galda sveces, Stāvsveces | ~01.12.2026 |
| 08.11.2026 09:00 ⏰ | [Korporatīvās Ziemassvētku dāvanas](https://marmarcandles.com/lv/korporativas-ziemassvetku-davanas-sveces/) | korporatīvās dāvanas, dāvanas darbiniekiem/klientiem | Sadarbības partneriem, Ziemassvētku sveces | ~05.12.2026 |
| 09.11.2026 09:00 ⏰ | [Ziemassvētku torte: kā to izrotāt ar svecēm](https://marmarcandles.com/lv/ziemassvetku-torte-sveces/) | ziemassvētku torte, sveces tortei | Tortes sveces | ~05.12.2026 |
| 10.11.2026 09:00 ⏰ | [Jaunā gada torte ar ciparu svecēm “2027”](https://marmarcandles.com/lv/jauna-gada-torte-ciparu-sveces-2027/) | ciparu sveces, jaunā gada torte | Ciparu sveces | ~10.12.2026 |

Публикация: исходник статьи — `blog/posts/<дата>-<тема>.py` (разметка через `blog/lib.py`), затем
`python3 tools/blog_publish.py blog/posts/<файл>.py` — проверяет ссылки, ставит отложенную публикацию (date_gmt), пишет SEO-поля Rank Math.
Режим (решение Алины 06.11.2026): **отложенная публикация пачками по 3–4 статьи, 1 статья в день в 9:00**. До праздников — только Рождество/Новый год, после — другие темы.
Сроки заказа к Рождеству (подтверждены Алиной): обычный — до 8.12, срочный (+5 €) — до 15.12.

## Очередь

| # | Тема | Данные (GSC LV авг–нояб 2026, заказы) | Когда |
|---|---|---|---|
| 5 | Dāvanas skolotājām un audzinātājām Ziemassvētkos | отдельный частый запрос | 11.11 |
| 6 | Slepenā Santas dāvanas līdz 5 € un 10 € | обмен подарками в офисе/классе | 12.11 |
| 7 | Ziemassvētku dekorācijas mājai ar svecēm | «svētku dekorācijas» поз. 4,7, 0 кликов | 13.11 |
| 8 | Bērnu Ziemassvētku ballīte: dekors un mazas dāvaniņas | фигурные свечи по 5 € | 14.11 |
| 9 | Jaungada galda dekors: zelts, melns un balts | новогодний стол | 15.11 |
| 10 | Bišu vaska, sojas vai parafīna svece — kuru dāvināt? | информационный | 16.11 |
| 11 | Kā droši dedzināt sveces svētkos un cik ilgi tās deg | безопасность, круглый год | 17.11 |
| 12 | Pēdējā brīža Ziemassvētku dāvanas | срочный заказ, самовывоз, подарочная карта | ~10.12 |

### После праздников (январь)
| 4 | Bērnu dzimšanas dienas torte: idejas pa vecumiem ar svecēm un toperiem | «tortes dzimšanas dienā» 68 показов, поз. 14; «dzimšanas dienas tortes bērniem» 36, поз. 7–9 | декабрь |
| 5 | Mazuļa mēnešsvētki: idejas katram mēnesim | набор «1–11 мес.» — бестселлер (3 заказа) | декабрь |
| 6 | Kūku dekorēšana mājās: triki ar svecēm un toperiem | «kūku dekorēšana» 51 показ, поз. 55 | декабрь |
| 7 | Bento torte un sveces | «bento cake» поз. 5,6; бенто-молды 100–200 € | январь |
| 8 | Sveces konditoriem | кондитеры покупают наборы цифр 0–9 | январь |
| 9 | Kāzu sveces 2027 (объединить 4 свадебные статьи + 301) | «dāvanas kāzu viesiem» поз. 10 | январь |

## Не писать новых статей — улучшать существующее
- «dzimšanas dienas svecītes»: 9 страниц конкурируют → объединить, а не добавлять.
- 3 статьи про свечи-цифры → одна сильная + 301.
- Категория «Ciparu sveces» (поз. 4, продаёт) → текст и FAQ на самой категории.
