# みまもりエージェント v0.4.0

- 初回接続を公開申請ルートへ対応
- `claim_key` を自動生成して保持
- トークン未設定でも「無料のみまもり開始」が可能
- 承認確認時に `site_token` を受け取り、自動で正式連携へ移行
- 正式連携後はサイト個別トークンで本同期を実行
- 詳細設定の説明文を自動連携前提へ更新
- onboarding 画面を状態別 UI に再構成
  - welcome
  - result
  - waiting
  - connected
  - local_only
  - not_approved
  - error_retry
- 主要操作を 1 状態 1 CTA に整理し、補助操作は「ほかにできること」へ分離
- 通信失敗時は顧客向け文言へ変換し、`error_retry` 状態を短時間保持する
- 無料配布向けに「診断で行うこと」「勝手にしないこと」「外部送信の条件」を初回画面と readme に明記
- 配布 ZIP のトップレベルフォルダは `mimamori-client-agent/` として、汎用的な `agent/` 名で展開されないようにする
