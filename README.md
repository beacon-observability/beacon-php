# Beacon PHP

Beacon PHP 是 Beacon Observability 基于完整 OpenTelemetry PHP Contrib 源码维护的 PHP 自动插桩与增强工程。仓库保留上游历史，不使用 GitHub Fork；Beacon 自有功能、测试、版本和发行流程在本仓库独立维护。

当前处于工程准备阶段，尚无正式发行。候选 Composer 包名为 `beacon-observability/beacon-php`，本仓库中的候选制品仅用于验证，不代表已经发布到 Packagist 或适合生产使用。

PHP 自动插桩由两部分组成：本仓库中的组件插桩包，以及基于 `zend_observer` 的 [`Beacon PHP Instrumentation`](https://github.com/beacon-observability/beacon-php-instrumentation) 原生扩展。两个仓库独立跟踪各自的 OpenTelemetry 上游，并通过固定提交联调。

## 开发入口

- [开发说明与工程边界](beacon/README.md)
- [源码来源与上游基线](beacon/upstream.lock.json)
- [同步 OpenTelemetry PHP Contrib](beacon/UPSTREAM.md)
- [发行准备](beacon/RELEASING.md)
- [Beacon Composer 候选包](beacon-package/)
- [OpenTelemetry PHP Contrib 组件](src/)
- [贡献指南](CONTRIBUTING.md)

日常 CI 只验证 Beacon 自有入口、候选包和元数据。采用新上游基线时，需按受影响范围运行对应 Contrib 组件的完整测试，不能用日常 CI 代替同步验收。

## Beacon Contributors

<p align="center">
  <a href="https://github.com/lrwh">
    <img src="https://avatars.githubusercontent.com/u/17264378?v=4" width="96" height="96" alt="Reid Liu">
    <br>
    Reid Liu
  </a>
</p>

## 产品与上游

- [Beacon 产品入口](https://github.com/beacon-observability/beacon)
- [OpenTelemetry PHP Contrib](https://github.com/open-telemetry/opentelemetry-php-contrib)
- [Beacon PHP Instrumentation Extension](https://github.com/beacon-observability/beacon-php-instrumentation)
- [OpenTelemetry PHP Instrumentation 上游](https://github.com/open-telemetry/opentelemetry-php-instrumentation)

仓库保留上游源码布局、历史、包名和[许可证](LICENSE)。只有 Beacon 自有 Composer 包使用 Beacon 名称；上游 `open-telemetry/*` 包不会通过修改版本号伪装成 Beacon 制品。
