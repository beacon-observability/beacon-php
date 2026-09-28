# 仓库协作约定

- 始终使用简体中文沟通。
- 本仓库是 `opentelemetry-php-contrib` 的独立下游，不使用 GitHub Fork；保留上游历史、目录结构和许可证。
- `origin` 只指向 `beacon-observability/beacon-php`，`upstream` 只用于获取官方更新，不得向上游推送。
- Beacon 自有代码、文档、测试和候选制品分别放在 `beacon/`、`beacon-package/` 及明确的 Beacon 组件目录中，不批量改写上游 Composer 包名和版本。
- 自动插桩扩展 `ext-opentelemetry` 来自独立上游仓库，不把扩展源码直接复制到本仓库。
- 日常 CI 保持最小范围；上游同步必须根据影响范围补跑对应组件测试，并记录验证结果。
- 未完成固定源码、候选制品安装、运行环境和接收端验证前，不宣称正式支持或发布。
