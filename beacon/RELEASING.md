# Beacon PHP 发行准备

对外产品名称为 Beacon PHP。候选 Composer 包名是 `beacon-observability/beacon-php`，正式标签计划使用 `beacon-vX.Y.Z`；上游 `open-telemetry/*` 子包继续使用各自版本，不批量替换为 Beacon 产品版本。

当前没有正式发布流程。只有完成以下事项后，才能创建 Packagist 包和发布工作流：

1. 确认 `beacon-observability` 的 Packagist 组织与包名归属，确定 GitHub/Packagist 发布权限和人工审批。
2. 明确首版组件范围。基础 Beacon 包不会强制安装 Laravel、Symfony、WordPress 等互斥框架；框架插桩必须由应用按需安装。
3. 固定 Contrib 提交、`beacon-php-instrumentation` 提交、官方扩展基线和 Composer 依赖，检查许可证及第三方声明。
4. 从固定提交构建候选归档，在 PHP 支持矩阵中全新安装并运行 `vendor/bin/beacon-php doctor`。
5. 对声明支持的组件运行单元、静态和集成测试，并使用目标 DataKit 版本完成 OTLP Trace 链路验收。
6. 记录制品摘要、已知限制、升级/回退方式和版本化使用文档。

## 版本规则

- `beacon/version.properties` 是产品开发版本的唯一手工入口。
- `beacon-package/src/Version.php` 是由检查脚本核对的代码副本。
- 开发版本使用 `X.Y.Z-dev`，候选版使用 `X.Y.Z-rc.N`，正式版使用 `X.Y.Z`。
- Composer 最终版本来自不可变 Git 标签；不得覆盖已发布的同版本制品。

## 候选制品

当前可以生成仅供验证的 Composer 归档：

```bash
php beacon/scripts/check-project.php
composer install --working-dir=beacon-package
composer check --working-dir=beacon-package
COMPOSER_ROOT_VERSION=0.1.0 composer archive \
  --working-dir=beacon-package --format=zip --dir=dist
```

构建成功只证明包元数据和基础运行检查通过，不表示已经发布，也不能代替框架组件及接收端验收。
