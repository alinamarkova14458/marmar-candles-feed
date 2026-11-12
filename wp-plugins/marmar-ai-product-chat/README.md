MarMar AI Sales Assistant v0.9.0

WooCommerce shopping assistant with grounded catalog retrieval, variation-aware number matching, soft recipient/occasion preferences, hard budget/size constraints, and OpenAI Responses API generation.


Version 1.2.0 adds guided variation selection: known constraints such as a requested number are locked, while only unresolved attributes and real compatible options are presented in-chat.


## Version 1.3.1 changes
- Fixed the WooCommerce save/duplicate product fatal error caused by `MarMar_AI_Product_Chat::invalidate_index()` being registered as a WordPress callback while declared `private`. It is now `public`.
- Added server-side conversation logging for every public chat request and AI response, grouped by a browser conversation ID.
- Added **Settings → AI Conversations** admin screen with search, full conversation view, product payload inspection, per-conversation deletion, and delete-all controls.
- Conversation logs are retained for 365 days by default and do not intentionally store IP addresses.
