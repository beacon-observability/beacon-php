# 同步 OpenTelemetry PHP Contrib

命令均从仓库根目录执行。`main` 是 Beacon 下游主线；官方 `main` 只用于发现更新，不能直接覆盖 Beacon 自有提交。PHP Contrib 的 Composer 子包独立发行，仓库没有可作为统一基线的新版本标签，因此本项目使用经过审查的完整上游提交作为同步基线，并在[基线文件](upstream.lock.json)中固定。

## Remote 配置

克隆后确认：

```bash
git remote -v
```

期望配置为：

```text
origin    https://github.com/beacon-observability/beacon-php.git
upstream  https://github.com/open-telemetry/opentelemetry-php-contrib.git
```

不存在 `upstream` 时添加并禁止误推：

```bash
git remote add upstream https://github.com/open-telemetry/opentelemetry-php-contrib.git
git remote set-url --push upstream DISABLED
git config remote.pushDefault origin
```

## 同步步骤

1. 获取官方主线并记录拟采用的完整提交：

   ```bash
   git fetch --no-tags upstream main
   git show --no-patch --format=fuller upstream/main
   ```

2. 在干净的 Beacon `main` 上建立同步分支，以合并提交方式合入固定提交，不使用目录覆盖或 squash 丢失上游来源：

   ```bash
   git switch main
   git switch -c sync/php-contrib-YYYYMMDD
   git merge --no-ff <reviewed-upstream-commit>
   ```

3. 解决冲突并保留 `beacon/`、`beacon-package/`、README 和 Beacon CI。检查上游新增的 Actions，不引入其拆分发布、机器人、Packagist 或组织专属凭证流程。
4. 运行 `php beacon/scripts/check-project.php`、候选包 CI，以及所有受影响子项目的静态检查与测试。自动插桩改动还要核对对应 `ext-opentelemetry` 正式版本；扩展升级需单独评审和验证。
5. 验证通过后才更新 `upstream.lock.json` 的 `upstream.commit`。扩展字段只在实际采用并完成验证后更新，不能仅因上游发布新版本自动修改。
6. 确认固定上游提交已进入当前历史：

   ```bash
   git merge-base --is-ancestor <reviewed-upstream-commit> HEAD
   ```

抓取、合并、测试和发行是不同状态。同步完成不表示 Beacon PHP 已发布，也不表示未测试组件获得支持承诺。
