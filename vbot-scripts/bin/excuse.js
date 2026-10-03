#!/usr/bin/env node
/**
 * excuse.js — 程序员甩锅专用 Excuse 生成器
 * 用法: excuse [风格]
 *
 * 风格: generic | bug | deploy | meeting | boss | chaos
 */

const excuses = {
  generic: [
    "这个 bug 昨天还在 CI 里绿着呢",
    "我本地明明跑通了",
    "肯定是谁动了生产环境的配置",
    "这功能上周还有人夸来着",
    "测试覆盖率 100%，不可能有问题",
    "之前一直好好的，不知道为什么突然...",
    "这个接口文档上就是这么写的",
    "产品需求描述得不清楚",
    "可能是网络抖动导致的",
    "服务器时间不对",
    "应该是运维那边的配置问题",
    "用户操作不当导致的",
    "这是已知的 edge case，正在修",
    "第三方 SDK 返回的数据格式变了",
    "容器重启一下就好了",
  ],
  bug: [
    "这是 feature，不是 bug",
    "这个行为符合设计文档（虽然没写）",
    "竞态条件，极小概率事件",
    "这叫 'undefined behavior'，符合规范",
    "是 Unicode 归一化的问题",
    "内存泄漏？谁让它跑那么久的",
    "浮点精度问题，IEEE 754 的锅",
    "正则回溯爆炸了，QA 为啥没测这个",
    "JSON 解析歧义，标准没说清楚",
    "时区问题，我一直用的是 UTC",
    "编码问题，文件肯定是 UTF-8",
    "正则写错了一个字符而已",
    "变量命名太糟糕了，谁写的",
    "这代码注释说是 'TODO: 优化'，不是 bug",
    "逻辑是对的但顺序错了",
  ],
  deploy: [
    "Blue-Green 切换的时候丢了几个请求",
    "灰度发布的时候 IP 白名单没更新",
    "CDN 缓存没刷",
    "Kubernetes 滚动更新的时候 pod 调度不均",
    "Helm Chart 版本冲突了",
    "Ingress Controller 重启导致 502",
    "Prometheus 抓不到 metrics 了",
    "证书过期... 等等，没过期啊？",
    "负载均衡器后端权重配置错了",
    "数据库连接池耗尽了",
    "队列积压，worker 来不及消费",
    "定时任务并发跑了两次",
    "配置中心推了个空配置",
    "容器镜像拉取超时",
    "日志采集 agent 崩了",
  ],
  meeting: [
    "会议太长，回来忘了自己在干啥",
    "Stand-up 的时候还没测完",
    "Pair programming 的时候对方改了我的代码",
    "Retro 提了这个，但优先级不够",
    "技术评审的时候说 scope creep 了",
    "Design Doc 还没合入就先上线了",
    "这个 refactor 太大了，需要先拆",
    "依赖的其他 team 还没提供 API",
    "这个 ticket 优先级后来被调低了",
    "Planning poker 的时候数字给错了",
    "Sprint goal 说的是 MVP，这个是 V2",
    "To-do list 太长了根本没看到这一条",
    "Code review 被 approve 了三次又改回来了",
  ],
  boss: [
    "这个需求太小了，不值得做",
    "这个功能用户根本不会用到",
    "竞品也没做这个，我们领先了",
    "这个属于技术债，可以下个季度再还",
    "投入产出比太低，不划算",
    "先看看用户数据再决定要不要做",
    "这个是 PM 的决定，我只是执行",
    "我们先 launch，再迭代优化",
    "这个已经 roadmap 上了，等排期",
    "资源有限，优先级需要重新评估",
    "做这个不如做那个重要",
    "这个要结合商业战略一起看",
    "用户调研还没做完",
    "等友商做了再抄... 不对，再参考",
    "这个方向可能不太对，再想想",
  ],
  chaos: [
    "服务器被家里的猫踢了电源",
    "保洁阿姨拔错了网线",
    "隔壁楼停电导致 UPS 切换",
    "光纤被挖断了（第三方的错）",
    "空调坏了，机房温度过高自动降频",
    "雷暴天气导致电磁干扰",
    "有人把 production 当 staging 用了",
    "Hotfix 热部署到 staging 上了",
    "Dockerfile 多打了个空格，镜像 hash 全变了",
    "git reset --hard 之后才发现有 uncommitted 的 work",
    "CI 跑了两遍，一遍 pass 一遍 fail，信哪个",
    "重启大法好，重启解决 90% 的问题（还有 10% 重启两遍）",
    "grep -r 的时候把整个 node_modules 给索引了",
    "删库跑路的脚本被 crontab 定时执行了",
    "显示器背后那颗神秘的红灯",
  ],
};

const styles = Object.keys(excuses);
const args = process.argv.slice(2);

function pick(arr) {
  return arr[Math.floor(Math.random() * arr.length)];
}

if (args.length === 0 || args[0] === 'random') {
  const all = styles.flatMap(s => excuses[s]);
  console.log('\n  🎭 ' + pick(all) + '\n');
  console.log('  甩锅风格提示: excuse generic|bug|deploy|meeting|boss|chaos\n');
} else if (args[0] === '--list' || args[0] === '-l') {
  styles.forEach(s => {
    console.log(`\n  📋 ${s.toUpperCase()}`);
    excuses[s].forEach(e => console.log(`    • ${e}`));
  });
  console.log();
} else if (args[0] === '--all' || args[0] === '-a') {
  const all = styles.flatMap(s => excuses[s]);
  all.forEach(e => console.log(`  • ${e}`));
  console.log(`\n  共 ${all.length} 条\n`);
} else if (styles.includes(args[0])) {
  const pool = excuses[args[0]];
  console.log('\n  🎭 ' + pick(pool) + '\n');
} else {
  console.log(`\n  未知风格: ${args[0]}`);
  console.log(`  可用: ${styles.join(', ')}\n`);
}
