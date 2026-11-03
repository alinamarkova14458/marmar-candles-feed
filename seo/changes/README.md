# Журнал правок сайта

Перед каждой правкой здесь сохраняется «было», чтобы её можно было откатить.

| Дата | Что | Как | Откат |
|---|---|---|---|
| 03.11.2026 | Корзина (страница 8): «Your cart is currently empty!» → «Tavs grozs pagaidām ir tukšs», «New in store» → «Jaunākās sveces», «You may be interested in…» → «Tev varētu patikt arī» | REST API, с «ок» Алины (UX пачка 1) | `2026-11-03-page-8-cart-before.html` |
| 03.11.2026 | Главная (страница 452): текст «Sveces Latvijā dažādiem svētkiem…» выровнен по левому краю вместо выравнивания по ширине | REST API, с «ок» Алины (UX пачка 1) | `2026-11-03-page-452-home-before.html` |
| — | Сниппет `site/snippets/marmar-ux-batch1.php` (блок под кнопкой, подсказка, подписи свотчей, SKU, cookies, чат, пустая корзина без ошибки) | Алина вставляет в WPCode | выключить сниппет в WPCode |
