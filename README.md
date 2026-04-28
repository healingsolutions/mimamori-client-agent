# みまもり無料診断

WordPress サイトの状態を無料で診断し、更新候補・基本設定・公開時に確認したい点をやさしく表示するクライアント側プラグインです。

このプラグインは、診断だけで更新・削除・設定変更を自動実行しません。必要な場合のみ、みまもりポータルへの開始申請や診断データの共有へ進めます。

## 主な機能

- 初回診断とやさしい診断サマリー
- 更新候補、基本設定、公開時の確認ポイント表示
- 診断履歴の保存
- 診断データのダウンロード
- 無料のみまもりサポート開始申請
- 承認後のみまもりポータル連携

## 配布方針

- Freemius で無料配布とアップデート管理を行います。
- みまもり HQ との接続認証は Freemius ライセンスではなく、サイトごとの `site_token` で扱います。
- HQ 側の運用ポータルはこのリポジトリには含めません。

## 開発メモ

- Plugin slug: `mimamori-client-agent`
- Main file: `mimamori-client-agent.php`
- Text domain: `mimamori-client-agent`
- Current version: `0.4.0`

## リリース運用

GitHub へ反映するとき:

```powershell
.\tools\ok-git.ps1 -Message "Update client plugin"
```

Freemius へアップロードするとき:

```powershell
$env:FREEMIUS_PRODUCT_ID="12345"
$env:FREEMIUS_API_TOKEN="..."
.\tools\ok-freemius.ps1 -ReleaseMode pending
```

Freemius の本番配信は、最初は `pending` でアップロードして管理画面で確認してから `released` に変更します。

配布 ZIP は `git archive` / GitHub Actions の Linux `zip` で作成し、開発用の `.github/`、`tools/`、`README.md` は含めません。
