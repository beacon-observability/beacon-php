# Beacon PHP 开发入口

Beacon PHP 以 OpenTelemetry PHP Contrib 为组件插桩主上游，保留完整 Git 历史并在同一源码树内维护 Beacon 增强。当前工程只完成下游仓库、固定基线、候选 Composer 包和最小 CI 的建立，尚未完成正式发行验收。

## 工程边界

| 内容 | 位置或来源 |
| --- | --- |
| 组件自动插桩、传播器、资源检测与辅助包 | 仓库根目录及 `src/` |
| Beacon 产品版本、同步和发行说明 | `beacon/` |
| Beacon Composer 候选包 | `beacon-package/` |
| PHP Hook 原生扩展 | 独立发布的 [`beacon-php-instrumentation v0.1.0`](https://github.com/beacon-observability/beacon-php-instrumentation/releases/tag/v0.1.0) |
| OpenTelemetry API、SDK 与 OTLP Exporter | Composer 上游依赖 |

PHP 自动插桩要求安装 Beacon 发行的 `ext-opentelemetry`，再按应用实际依赖安装一个或多个组件插桩包。Beacon 后续新增或修复组件时，应在本仓库集中维护实现、兼容范围和测试，不以等待上游合并作为业务修复前提；通用修复仍应并行贡献上游。手动插桩只依赖 OpenTelemetry API/SDK，不加载扩展也能使用。

## 本地验证

需要 PHP 8.2 或更高版本、Composer 2 和 `ext-opentelemetry`：

```bash
php beacon/scripts/check-project.php
composer validate --no-check-publish
composer validate --working-dir=beacon-package --strict
composer install --working-dir=beacon-package
composer check --working-dir=beacon-package
COMPOSER_ROOT_VERSION=0.1.0 composer archive \
  --working-dir=beacon-package --format=zip --dir=dist
```

上述命令只覆盖 Beacon 自有候选包。修改或同步 `src/` 下的组件时，还必须进入对应子项目运行 Composer 安装、静态检查和 PHPUnit；组件可能依赖数据库、消息系统或不同 PHP/框架版本，具体以其 `composer.json`、README 和上游测试矩阵为准。

## 当前限制

- `beacon-observability/beacon-php` 尚未注册到 Packagist。
- 候选包只建立 Beacon 发行身份、基础 OTel SDK/OTLP 依赖和诊断命令，不会自动安装所有框架组件；当前 CI 从 `v0.1.0` 的固定发行提交构建 Beacon 原生扩展进行联调。
- 尚未完成完整 Contrib 测试矩阵、Packagist 发布权限、DataKit 接收端链路和升级回退验收。
- 暂无 Beacon PHP 正式版本、安装入口或生产支持承诺。
