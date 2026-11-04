# 参与开发 / Contributing

请先阅读 [中文说明](README.md) 或 [English README](docs/README.en.md)。

1. 使用 Composer 声明的 PHP 和依赖版本，安装开发依赖。
2. 运行 `composer check`；修改格式可用 `composer format:fix`。
3. 对行为修改补充相关测试，并完成宿主集成验收；没有独立测试的包须在 PR 中明确验证路径。
4. 不提交 `composer.lock`、`vendor/`、凭据、生成缓存或业务数据。依赖约束变化须同步中英文文档。
5. 提交 PR，说明目的、验证结果及未验证的环境。不要直接提交或发布版本。

Use the declared PHP/dependency versions. Run `composer check` and relevant tests; use `composer format:fix` for formatting. Include host integration evidence and disclose missing standalone coverage. Never commit Composer lockfiles, dependencies, secrets, caches or business data. Update both language documents when requirements change.

## CI 与私有依赖 / CI and private dependencies

CI 检查 PHP 8.4/8.5、Composer 清单、语法及 Pint；有独立套件的包额外运行测试。独立仓库没有相邻开发包，CI 会移除开发用 path repositories。

需要私有依赖时，在 GitHub 仓库变量 `PACKAGE_REPOSITORIES` 配置 Composer repository 对象的 JSON 数组；在 Secret `COMPOSER_AUTH` 配置 Composer 认证 JSON。不要在变量或源码中放凭据。变量示例：

```json
[{"type":"composer","url":"https://composer.example.com"}]
```

替换成实际有权访问的软件源，也可使用多个 VCS repository；软件源须覆盖传递依赖。CI 只使用读取权限，不提交生成的锁文件、不修改版本或自动推送。

CI checks PHP 8.4/8.5, Composer metadata, syntax and Pint, plus standalone tests where available. Configure the `PACKAGE_REPOSITORIES` repository variable with a JSON list of authorized Composer repositories, and the `COMPOSER_AUTH` secret for credentials. Local sibling path repositories are removed in CI; supply sources for transitive private dependencies too. CI never publishes, commits lockfiles or pushes changes.
