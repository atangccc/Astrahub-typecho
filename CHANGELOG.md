# 更新日志

本项目所有重要变更都记录在这里。

格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，
版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [1.1.0] - 2026-06-17

### 变更

- 对齐 Halo、WP、Typecho 三端的友链星球卡片展示。
- 星系排行增加皇冠提示，展示热门排序说明。
- 星系名称、星系数、描述长度、本站卡片样式统一。
- 收藏按钮保留为裸五角星样式。
- 友链邀请管理移除旧“已送达/投递状态”展示，改为审核结果展示。
- 收紧邀请列表字段间距，优化卡片可读性。
- 清理多处乱码文案和旧展示残留。

## [1.1.1] - 2026-06-24

### 修复

- 友链删除逻辑改为按关系状态分流：单向关注仅删除本站到对方的关注关系，互相关注才执行解除关系。
- 新增 `friendRemoveFollow` 接口，Typecho 端可直接调用 Hub 的单向取消关注能力，并同步删除本地友链记录。
- 调整友链星球中的删除确认文案：单向删除不再提示邮件通知，互关解除才提示会通知对方。
- 修正删除成功与无变化提示文案，避免后台出现“关系已解除”乱码。
- 微调友链星球的热点序号徽标样式，和其他端保持一致。

## [1.0.0] - 2026-06-15

### 初始发布

- Typecho 端接入 AstraHub 星链生态。
- 支持站点接入、友链邀请、本地友链管理、访客申请、星球页、关系图谱、资讯聚合和挂件注入。

[1.1.0]: https://github.com/atangccc/Astrahub-typecho/releases/tag/v1.1.0
[1.0.0]: https://github.com/atangccc/Astrahub-typecho/releases/tag/v1.0.0
[1.1.1]: https://github.com/atangccc/Astrahub-typecho/releases/tag/v1.1.1
