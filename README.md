<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Laravel Filament Audit

简体中文 | [English](docs/README.en.md)

操作审计，适用于 **PHP 8.4+、Laravel 13、Filament 5**。

## 功能与边界

可选审计包，按成功业务操作采集，事务提交后投递独立队列，通过 Driver 写入。默认关闭，不使用模型 Observer。

## 安装

先在宿主 Composer 配置本私有包及所需 `fayne/*` 依赖的软件源，再执行：

```bash
composer require fayne/laravel-filament-audit
php artisan vendor:publish --tag=filament-audit-config
php artisan migrate
```

Laravel 自动发现服务提供者。仅提供字段的包无需注册 Panel 插件；业务包按下面的示例注册。依赖版本以 [composer.json](composer.json) 为准，不要复制开发宿主的私有仓库凭据。

## 使用与配置

业务包通过 CMS Core 的操作事件接入，无需依赖本审计包。

1. 执行迁移：`php artisan migrate`。已有系统可仅迁移 `packages/laravel-filament-audit/database/migrations`。
2. 发布配置：`php artisan vendor:publish --tag=filament-audit-config`。本宿主已有配置文件，不必覆盖。
3. 设置 `FILAMENT_AUDIT_ENABLED=true`；配置异步队列连接，默认 `database`、队列名 `audit`。
4. 执行 `php artisan audit:permissions`，创建 `platform.audit.view` 和 `tenant.audit.view` 权限定义，再按授权范围分配。不会自动给现有角色授权；平台 super-admin 遵循已有授权机制。
5. 执行 `php artisan audit:check`。
6. 启动独立消费者：`php artisan queue:work database --queue=audit`。使用 Redis 时设置 `FILAMENT_AUDIT_QUEUE_CONNECTION=redis` 并配置相应消费者。现有 Horizon 默认只消费 default 队列，需要显式增加 audit 队列。
7. 配置 Laravel scheduler，包会注册每日 `audit:prune`。

未选择留存策略前 `retention_days=null`，不会自动删除。设置正整数后，按操作时间分批永久删除到期日志。本期无归档功能。时间存储为 UTC。

默认 `visibility=platform_only`；设置 `tenant_scoped` 后，有审计权限的租户管理员可查看本租户日志。全局和跨租户操作仅平台可见。

默认关闭；启用、关闭或修改配置后，应刷新配置缓存并按项目流程重启 Octane / 队列进程。

## 新模块接入标准

参见宿主 `docs/operation-audit-integration-standard.md`。不要在业务模块导入 `Fayne\Audit`。

标准 Resource 页面使用 `Fayne\CMS\Filament\Operations\CreateRecord` / `EditRecord`。已有 hooks 继续正常执行，内部授权等操作不重复记录。

模型可提供 `operationAuditPayload(array $payload): array`，明确筛选标准表单和 Action 的审计字段；该筛选在队列派发前执行。二维码模块使用允许列表排除 URL、scene、路径、扩展数据和图片内容。此方法不触发模型监听，也不替代业务操作边界。

默认 Filament CreateAction、EditAction、DeleteAction、ForceDeleteAction、RestoreAction、ReplicateAction 由包集中适配。独立 QR 包继续使用原生页面，由框架事件接入，避免新增对 cms-core 的循环依赖。

特殊业务操作用 cms-core 的 `Operations::run()`，或成功后调用 `completed()`。不要根据自定义 Action 名称推断是否写入。标准批量删除、强制删除和恢复使用 cms-core 同名 Operations 组件，汇总实际成功对象后记录一条。自定义批量逻辑在最外层按接入规范记录；未提供 Observer 或按模型记录的兜底。

登录、登出自动监听成功事件；密码、令牌等默认从嵌套 payload 中排除，过滤发生在入队之前。模块敏感字段通过 `excluded_fields` 增补，支持字段名和点路径通配符。任意对象、上传文件和资源不进入 payload。

超过 `max_payload_bytes` 的事件会被拒绝并报告，不无声截断；业务不受影响。需要记录大内容时显式调整上限。

## 自定义 Driver

在服务提供者中使用 `app(\Fayne\Audit\Drivers\DriverManager::class)->extend('custom', fn ($app) => $app->make(CustomDriver::class))`。

Driver 实现 `Fayne\Audit\Contracts\AuditDriver::write(array $entry): void`；事件编号用于幂等。失败抛出异常，由 Job 重试。设置 `filament-audit.driver=custom` 即可切换。查询界面和清理命令目前仅面向 database，不支持查询远程 Driver。

## 故障与卸载

派发失败只写应用错误日志，不返回业务失败。Job 的写入失败交给 Laravel 重试和 failed_jobs 机制处理；错误日志不附带业务 payload。

存在业务提交到队列接收之间的丢失窗口；本期不保证强一致投递，不引入 outbox。

关闭时不采集、不派发、不清理，已入队任务执行时检查开关并跳过。卸载前先关闭采集、处理或清除独立审计队列，再移除包；不要在积压任务仍引用审计 Job 时直接删除包。

## 测试与上线

在开发仓库根目录执行：

```bash
php packages/laravel-filament-cms-core/vendor/bin/phpunit -c packages/laravel-filament-audit/phpunit.xml.dist
```

测试使用 Core 的 Testbench 环境，不迁移宿主业务数据库；实际队列消费者和生产留存策略需单独验收。

## 合作与贡献

通过 Issue 描述需求或缺陷，提供复现步骤、版本和预期行为；提交合并请求时补充相关验证，并说明尚未验证的环境。请勿提交密钥或业务敏感数据。商业合作及授权请联系 [Fayne Guo](mailto:fayne.guo@gmail.com)。

## 许可证

采用[专有许可证](LICENSE.md)。未经授权不得复制、分发或使用；第三方依赖仍遵循各自许可证。

## 开发与安全

[开发与 CI 约定](CONTRIBUTING.md) · [安全报告](SECURITY.md)。运行 `composer check` 检查语法和格式；有独立套件时同时运行测试。包不提交 `composer.lock`。

`composer test` 运行独立启动测试；上述完整业务集成套件仍在开发宿主运行，两者不等同。
