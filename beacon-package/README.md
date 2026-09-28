# Beacon PHP Composer Package

这是 Beacon PHP 的候选 Composer 包，当前仅用于开发和制品验证，尚未发布到 Packagist。

包中包含 Beacon 版本身份和 `beacon-php doctor` 诊断命令，并固定基础 OpenTelemetry API、SDK、OTLP Exporter 与 `ext-opentelemetry` 约束。Laravel、Symfony、Guzzle、PDO 等组件插桩必须由应用按需安装，避免基础包强制引入互斥框架。

正式安装方式将在首次发行和公开索引复验完成后提供。
