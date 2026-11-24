#!/usr/bin/env node
/**
 * GTA VI 移植社区浪报 CLI
 * 作者: VBot
 * 用途: GTA VI 移植社区浪报 CLI — 汇总全网正在把 GTA VI 搬上 PC 的项目
 * 更新日期: 2026-11-24
 *
 * 用法:
 *   node gta.js          彩色文本卡片
 *   node gta.js --json   纯 JSON（便于脚本消费）
 *
 * 零依赖，CommonJS，node >= 12 即可运行。
 */

'use strict';

// ── 硬编码数据（可手动更新，star 数为 2026-11-24 快照）─────────────────────
const PROJECTS = [
  {
    name: 'VIctory',
    repo: 'Marvisak/VIctory',
    stars: 8227,
    status: '不能玩',
    desc: 'x86-64 原生跑，能启动并进世界，渲染管线还没点亮 —— 目前全网最接近 GTA VI 上 PC 的项目',
    url: 'https://github.com/Marvisak/VIctory',
  },
  {
    name: 'xbox-emulator',
    repo: 'omonkeyz/xbox-emulator',
    stars: 1743,
    status: '研究中',
    desc: 'Xbox Series X|S 模拟器进度笔记，把主机版构建搬上 PC 的第一手资料',
    url: 'https://github.com/omonkeyz/xbox-emulator',
  },
  {
    name: 'portwatch-vi',
    repo: '4lwayz/portwatch-vi',
    stars: 1653,
    status: '追踪站',
    desc: '每 3 小时自动刷新的移植进度追踪站，社区浪报的汇总入口',
    url: 'https://github.com/4lwayz/portwatch-vi',
  },
  {
    name: 'Leonida Legacy',
    repo: 'ProjectLeonida/leonida',
    stars: 1097,
    status: '重编译中',
    desc: '静态重编译到 PC 平台，走 SPIR-V / Vulkan 图形栈路线',
    url: 'https://github.com/ProjectLeonida/leonida',
  },
];

// ── 输出工具 ───────────────────────────────────────────────────────────────
const useColor = process.stdout.isTTY && !process.env.NO_COLOR;
const C = (code) => (s) => (useColor ? `\x1b[${code}m${s}\x1b[0m` : String(s));
const bold = C('1');
const dim = C('2');
const cyan = C('36');
const yellow = C('33');
const green = C('32');
const magenta = C('35');
const red = C('31');

const TITLE = [
  '  ██████╗ ████████╗ █████╗     ██╗   ██╗██╗',
  ' ██╔════╝ ╚══██╔══╝██╔══██╗    ██║   ██║██║',
  ' ██║  ███╗   ██║   ███████║    ██║   ██║██║',
  ' ██║   ██║   ██║   ██╔══██║    ╚██╗ ██╔╝██║',
  ' ╚██████╔╝   ██║   ██║  ██║     ╚████╔╝ ██║',
  '  ╚═════╝    ╚═╝   ╚═╝  ╚═╝      ╚═══╝  ╚═╝',
  '            P O R T   W A T C H   ·   C O M M U N I T Y',
].join('\n');

const SPOTLIGHT =
  '目前全网最接近「GTA VI 上 PC」的项目：VIctory（Marvisak/VIctory）— 已能进世界，只差渲染点亮';

function sortedProjects() {
  return [...PROJECTS].sort((a, b) => b.stars - a.stars);
}

function pad(str, width) {
  // 简单按可见字符长度补空格（中文按 2 宽度近似处理）
  const visible = [...str].reduce(
    (n, ch) => n + (/[\u3000-\u9fff\uff00-\uffef]/.test(ch) ? 2 : 1),
    0
  );
  return str + ' '.repeat(Math.max(0, width - visible));
}

function renderText() {
  const list = sortedProjects();
  const lines = [];

  lines.push('');
  lines.push(magenta(TITLE));
  lines.push('');
  lines.push(yellow('★ ') + bold(SPOTLIGHT));
  lines.push(dim('─'.repeat(64)));
  lines.push('');

  const nameWidth = 20;
  const statusWidth = 10;

  list.forEach((p, i) => {
    const idx = dim(String(i + 1).padStart(2, ' ') + '.');
    const name = bold(cyan(pad(p.name, nameWidth)));
    const stars = yellow(pad('★ ' + p.stars.toLocaleString('en-US'), 10));
    const status = red('[' + p.status + ']');
    lines.push(`${idx} ${name} ${stars} ${status}`);
    lines.push(`    ${dim(p.repo)}`);
    lines.push(`    ${pad(p.desc, 0)}`);
    lines.push('');
  });

  lines.push(dim('─'.repeat(64)));
  lines.push(
    green('↑ ') +
      dim('star 数来自各仓库公开快照 · 数据更新 2026-11-24 · ') +
      dim('node gta.js --json 可获取结构化输出')
  );
  lines.push('');

  return lines.join('\n');
}

function renderJson() {
  const list = sortedProjects().map((p, i) => ({
    rank: i + 1,
    name: p.name,
    repo: p.repo,
    stars: p.stars,
    status: p.status,
    desc: p.desc,
    url: p.url,
  }));

  return JSON.stringify(
    {
      title: 'GTA VI PORT WATCH',
      author: 'VBot',
      updated: '2026-11-24',
      spotlight: {
        name: 'VIctory',
        repo: 'Marvisak/VIctory',
        note: '全网最接近 GTA VI 上 PC 的项目，已能进世界，渲染未点亮',
      },
      projects: list,
    },
    null,
    2
  );
}

function main() {
  const args = process.argv.slice(2);
  const json = args.includes('--json') || args.includes('-j');
  process.stdout.write((json ? renderJson() : renderText()) + '\n');
}

main();
