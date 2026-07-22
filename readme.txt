=== Soleman Auto Hide Checkout Field ===
Contributors: soleman
Donate link: https://soleman.tw
Tags: woocommerce, checkout, payment, fields, hide
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

依 WooCommerce 付款方式動態顯示或隱藏結帳欄位，支援 Classic 與 Block Checkout。

== Description ==

Soleman Auto Hide Checkout Field 可讓你在 WooCommerce 設定中，針對每種付款方式勾選要顯示的結帳欄位（Billing / Shipping / Additional）。

功能重點：

* 讀取已啟用的 WooCommerce 付款方式
* 讀取 WooCommerce 預設欄位與 Checkout Field Editor Pro（THWCFD）自訂欄位
* 整合於 WooCommerce → 設定 →「動態結帳欄位」分頁
* 勾選 = 顯示；未設定的付款方式預設顯示全部欄位
* 隱藏欄位時一併取消必填驗證，避免前端隱藏後仍被擋下單
* 支援 Classic Checkout 與 Block Checkout

== Installation ==

1. 上傳外掛資料夾至 `/wp-content/plugins/`
2. 在 WordPress 後台啟用「Soleman Auto Hide Checkout Field」
3. 前往 WooCommerce → 設定 → 動態結帳欄位 進行設定

== Frequently Asked Questions ==

= 需要 Checkout Field Editor Pro 嗎？ =

建議安裝以讀取自訂欄位；若未安裝，仍會使用 WooCommerce 預設結帳欄位。

= 尚未儲存某付款方式設定時會怎樣？ =

前端會顯示全部欄位。

== Changelog ==

= 1.0.0 =
* 初始版本
