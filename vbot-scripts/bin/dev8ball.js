#!/usr/bin/env node
/**
 * dev8ball — 专治技术选型纠结症
 * 用法: npx dev8ball "该用 TypeScript 还是 Go?"
 */

const responses = {
  strongYes: [
    "💪 毫无疑问，就是它了！",
    "🔥 Yes! 代码已经在燃烧了！",
    "🚀 冲！机会不等人！",
    "⚡ 答案写在闪电里：YES！",
    "🎯 命中注定，用这个！",
    "🦄 独角兽都点头了，放心用！",
    "🛡️ 这个选择能抗住未来的子弹！",
  ],
  yes: [
    "👍 听起来不错，可以试试",
    "✅ 合理，先跑起来再说",
    "👌 大方向没错，先用着",
    "🌱 好选择，边走边调整",
    "🎪 试试看，错了再改也不迟",
    "🔧 这个工具箱够用，动手吧",
  ],
  uncertain: [
    "🤔 这题超纲了，让我算算...",
    "🌀 宇宙正在思考，请稍候...",
    "🐉 龙在沉睡，改天再问",
    "🎭 命运之轮转不动，再想想",
    "☁️ 云里雾里，问得更具体些？",
    "🧘 我选择冥想，你自己定",
    "🍀 好坏参半，抛个硬币决定？",
  ],
  no: [
    "🚫 这个方向可能有问题",
    "⚠️ 三思，可能有坑",
    "🪦 这个坟别挖，回头是岸",
    "🧊 冷静，这主意有点凉",
    "🔙 退一步，可能有更好的",
    "🦈 前面有鲨鱼，换条路",
    "🚧 此路不通，考虑别的？",
  ],
  strongNo: [
    "❌ No. 这是明确的否定",
    "💀 答案之书说：绝对不要！",
    "🔥 这个栈会烧穿你的时间",
    "🕳️ 前面是个大坑，别跳",
    "⚰️ 埋了吧，别挣扎了",
    "🚷 此门不通，请绕行",
    "🌋 火山要喷了，跑！",
  ],
};

const pool = [
  ...responses.strongYes,
  ...responses.strongYes,
  ...responses.yes,
  ...responses.yes,
  ...responses.yes,
  ...responses.uncertain,
  ...responses.uncertain,
  ...responses.yes,
  ...responses.no,
  ...responses.strongNo,
  ...responses.strongNo,
];

function shake() {
  return pool[Math.floor(Math.random() * pool.length)];
}

function ask(question) {
  if (!question || question.trim().length < 3) {
    console.log("❓ 问个具体点的问题吧，比如：dev8ball \"该用 PostgreSQL 还是 MongoDB？\"");
    process.exit(1);
  }
  console.log("\n🎱 Dev8Ball 正在思考你的问题...\n");
  console.log(`   "${question}"`);
  console.log("\n   " + "─".repeat(40));
  setTimeout(() => {
    console.log("   " + shake());
    console.log("   " + "─".repeat(40) + "\n");
  }, 600);
}

const args = process.argv.slice(2);
if (args.length === 0) {
  console.log(`
🎱 Dev8Ball — 技术选型纠结终结者

用法:
  dev8ball "你的技术问题"

示例:
  dev8ball "该用 TypeScript 还是 Go？"
  dev8ball "Redis 还是 Memcached？"
  dev8ball "Next.js 还是 Nuxt？"

用 --list 查看所有答案类型
`);
  process.exit(0);
}

if (args[0] === "--list") {
  console.log("📋 Dev8Ball 答案库：\n");
  Object.entries(responses).forEach(([category, list]) => {
    const labels = {
      strongYes: "🔴 强力 Yes",
      yes: "🟡 Yes",
      uncertain: "🟣 不确定",
      no: "🟠 No",
      strongNo: "🔴 强力 No",
    };
    console.log(`${labels[category]} (${list.length}条):`);
    list.forEach(r => console.log(`  ${r}`));
    console.log();
  });
  process.exit(0);
}

ask(args.join(" "));
