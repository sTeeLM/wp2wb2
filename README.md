# wp2wb

发布文章时自动将文章同步发布到新浪微博

## 插件说明

wp2wb 将你的 WordPress 网站与新浪微博关联，在发布文章时自动将文章同步发布到新浪微博，并且可以选择以普通微博方式发布或者头条文章方式发布。

使用 wp2wb 需要先在 [新浪开放平台](http://open.weibo.com) 创建网站网页应用。


## 安装插件 

1. 下载插件 `zip` 压缩包，解压并上传到网站 `/wp-content/plugins` 目录，或者通过在 [插件中心](https://wordpress.org/plugins/wp2wb/) 在线下载安装
1. 在 `插件->已安装的插件` 中激活插件
1. 在 `设置->同步微博设置` 中按相关提示设置插件。


## 插件设置

1. 填写新浪 App Key 与 App Secret
![screenshot-1](./assets/screenshot-1.png)
2. 设置应用授权回调页并进行授权验证
![screenshot-2](./assets/screenshot-2.png)
3. 验证成功
![screenshot-3](./assets/screenshot-3.png)
4. 开启同步，并选择发布微博类型
![screenshot-4](./assets/screenshot-4.png)

## Frequently Asked Questions

### 新浪微博 API 安全域名

如果您在新浪开放平台申请的应用内设置了安全域名，请保证您网站使用域名与所绑定安全域名一致。安全域名在“我的应用 － 应用信息 － 基本应用信息编辑 － 安全域名”里设置。

### 头条文章

* 新浪微博头条文章不支持 `<pre>` 和 `<code>` 标签，在此我将这两个标签替换为 `<blockquote>` 和 `<i>`，可能会造成发布格式与原文格式不一致的情况。
* 新浪头条文章每日发文数量有限制，超出数量则无法同步。

### 内容转图片（sTeeLM）
* 新浪普通文章，支持post内容转图片，随文章一起发布的功能
* 在插件安装之后，需要在插件目录中运行：composer require mpdf/mpdf安装mpdf库
