const fs = require('fs');
const path = require('path');

// Output directory
const outDir = __dirname;

// 1. Manifest for Figma Plugin
const manifest = {
  name: "SISINDEN UI/UX Mobile Generator",
  id: "sisinden-mobile-ui-gen",
  api: "1.0.0",
  main: "code.js",
  capabilities: [],
  enableProposedApi: false,
  editorType: ["figma"]
};

fs.writeFileSync(path.join(outDir, 'manifest.json'), JSON.stringify(manifest, null, 2), 'utf8');

// 2. Figma Plugin code.js
const codeJs = `
// SISINDEN Mobile UI/UX Generator for Figma
// Referensi: eCommerce UI Kit Mobile Design (High Quality Modern App)
// Dibuat otomatis untuk kanvas SISINDEN Denintel Koarmada 2

async function run() {
  await figma.loadFontAsync({ family: "Inter", style: "Regular" });
  await figma.loadFontAsync({ family: "Inter", style: "Medium" });
  await figma.loadFontAsync({ family: "Inter", style: "Semi Bold" });
  await figma.loadFontAsync({ family: "Inter", style: "Bold" });

  const page = figma.currentPage;
  
  // Warna Utama dari Referensi UI Kit & SISINDEN
  const COLOR_BG = { r: 0.957, g: 0.973, b: 0.988 }; // Soft Ice Blue #F4F8FB
  const COLOR_WHITE = { r: 1, g: 1, b: 1 };
  const COLOR_NAVY_DARK = { r: 0.043, g: 0.106, b: 0.239 }; // #0B1B3D
  const COLOR_NAVY_LIGHT = { r: 0.063, g: 0.165, b: 0.420 }; // #102A6B
  const COLOR_GOLD = { r: 0.898, g: 0.663, b: 0.235 }; // #E5A93C
  const COLOR_TEXT_MAIN = { r: 0.059, g: 0.090, b: 0.165 }; // #0F172A
  const COLOR_TEXT_MUTED = { r: 0.392, g: 0.455, b: 0.545 }; // #64748B
  const COLOR_TEAL_LIGHT = { r: 0.882, g: 0.965, b: 0.945 }; // #E1F7F1
  const COLOR_TEAL_DARK = { r: 0.0, g: 0.659, b: 0.518 }; // #00A884
  const COLOR_BLUE_LIGHT = { r: 0.898, g: 0.949, b: 0.992 }; // #E5F2FD
  const COLOR_BLUE_DARK = { r: 0.008, g: 0.518, b: 0.780 }; // #0284C7
  const COLOR_RED_LIGHT = { r: 0.996, g: 0.925, b: 0.933 }; // #FEECEE
  const COLOR_RED_DARK = { r: 0.937, g: 0.267, b: 0.267 }; // #EF4444
  const COLOR_BORDER = { r: 0.886, g: 0.914, b: 0.941 }; // #E2E8F0

  function createShadow() {
    return [{
      type: "DROP_SHADOW",
      visible: true,
      color: { r: 0.04, g: 0.10, b: 0.24, a: 0.06 },
      offset: { x: 0, y: 6 },
      radius: 16,
      spread: 0,
      blendMode: "NORMAL"
    }];
  }

  // --- FRAME 1: DASHBOARD ---
  const frameDashboard = figma.createFrame();
  frameDashboard.name = "01 - SISINDEN Dashboard (eCommerce Style)";
  frameDashboard.resize(430, 932);
  frameDashboard.x = 0;
  frameDashboard.y = 0;
  frameDashboard.cornerRadius = 40;
  frameDashboard.fills = [{ type: "SOLID", color: COLOR_BG }];

  // Status Bar
  const statusBar = figma.createText();
  statusBar.characters = "09:41";
  statusBar.fontName = { family: "Inter", style: "Semi Bold" };
  statusBar.fontSize = 15;
  statusBar.fills = [{ type: "SOLID", color: COLOR_TEXT_MAIN }];
  statusBar.x = 36;
  statusBar.y = 20;
  frameDashboard.appendChild(statusBar);

  // Top Header (Greeting + Profile)
  const headerCard = figma.createFrame();
  headerCard.name = "Top Header";
  headerCard.resize(382, 60);
  headerCard.x = 24;
  headerCard.y = 60;
  headerCard.fills = [];
  headerCard.layoutMode = "HORIZONTAL";
  headerCard.primaryAxisAlignItems = "SPACE_BETWEEN";
  headerCard.counterAxisAlignItems = "CENTER";

  const greetingFrame = figma.createFrame();
  greetingFrame.name = "Greeting";
  greetingFrame.layoutMode = "VERTICAL";
  greetingFrame.itemSpacing = 2;
  greetingFrame.fills = [];
  
  const greetingSubtitle = figma.createText();
  greetingSubtitle.characters = "Selamat Pagi,";
  greetingSubtitle.fontSize = 13;
  greetingSubtitle.fontName = { family: "Inter", style: "Regular" };
  greetingSubtitle.fills = [{ type: "SOLID", color: COLOR_TEXT_MUTED }];
  greetingFrame.appendChild(greetingSubtitle);

  const greetingTitle = figma.createText();
  greetingTitle.characters = "Mayor Laut (P) Hendra";
  greetingTitle.fontSize = 18;
  greetingTitle.fontName = { family: "Inter", style: "Bold" };
  greetingTitle.fills = [{ type: "SOLID", color: COLOR_TEXT_MAIN }];
  greetingFrame.appendChild(greetingTitle);

  headerCard.appendChild(greetingFrame);

  // Avatar / Icon Notifikasi
  const notifBadge = figma.createFrame();
  notifBadge.name = "Notif Icon";
  notifBadge.resize(44, 44);
  notifBadge.cornerRadius = 22;
  notifBadge.fills = [{ type: "SOLID", color: COLOR_WHITE }];
  notifBadge.effects = createShadow();
  headerCard.appendChild(notifBadge);
  frameDashboard.appendChild(headerCard);

  // Hero Card Saldo (Deep Navy Gradient - Style eCommerce)
  const heroCard = figma.createFrame();
  heroCard.name = "Hero Balance Card";
  heroCard.resize(382, 190);
  heroCard.x = 24;
  heroCard.y = 136;
  heroCard.cornerRadius = 24;
  heroCard.fills = [{
    type: "GRADIENT_LINEAR",
    gradientHandlePositions: [{ x: 0, y: 0 }, { x: 1, y: 1 }, { x: 0, y: 1 }],
    gradientStops: [
      { color: { ...COLOR_NAVY_DARK, a: 1 }, position: 0 },
      { color: { ...COLOR_NAVY_LIGHT, a: 1 }, position: 1 }
    ]
  }];
  heroCard.effects = [{
    type: "DROP_SHADOW",
    visible: true,
    color: { r: 0.04, g: 0.10, b: 0.24, a: 0.25 },
    offset: { x: 0, y: 12 },
    radius: 24,
    spread: 0,
    blendMode: "NORMAL"
  }];
  heroCard.paddingLeft = 24;
  heroCard.paddingRight = 24;
  heroCard.paddingTop = 24;
  heroCard.paddingBottom = 24;
  heroCard.layoutMode = "VERTICAL";
  heroCard.itemSpacing = 16;

  const heroTop = figma.createFrame();
  heroTop.layoutMode = "HORIZONTAL";
  heroTop.primaryAxisAlignItems = "SPACE_BETWEEN";
  heroTop.counterAxisAlignItems = "CENTER";
  heroTop.fills = [];
  heroTop.resize(334, 24);

  const heroLabel = figma.createText();
  heroLabel.characters = "SALDO KAS UTAMA DENINTEL";
  heroLabel.fontSize = 11;
  heroLabel.fontName = { family: "Inter", style: "Semi Bold" };
  heroLabel.fills = [{ type: "SOLID", color: { r: 0.8, g: 0.85, b: 0.95 } }];
  heroTop.appendChild(heroLabel);

  const heroPill = figma.createFrame();
  heroPill.cornerRadius = 12;
  heroPill.fills = [{ type: "SOLID", color: { r: 1, g: 1, b: 1 } }];
  heroPill.fills[0].color = { r: 0.2, g: 0.35, b: 0.7 };
  heroPill.paddingLeft = 8;
  heroPill.paddingRight = 8;
  heroPill.paddingTop = 4;
  heroPill.paddingBottom = 4;
  const heroPillText = figma.createText();
  heroPillText.characters = "Otorisasi Aktif";
  heroPillText.fontSize = 10;
  heroPillText.fontName = { family: "Inter", style: "Medium" };
  heroPillText.fills = [{ type: "SOLID", color: COLOR_WHITE }];
  heroPill.appendChild(heroPillText);
  heroTop.appendChild(heroPill);
  heroCard.appendChild(heroTop);

  const heroBalance = figma.createText();
  heroBalance.characters = "Rp 128.450.000";
  heroBalance.fontSize = 28;
  heroBalance.fontName = { family: "Inter", style: "Bold" };
  heroBalance.fills = [{ type: "SOLID", color: COLOR_WHITE }];
  heroCard.appendChild(heroBalance);

  const heroFooter = figma.createFrame();
  heroFooter.layoutMode = "HORIZONTAL";
  heroFooter.primaryAxisAlignItems = "SPACE_BETWEEN";
  heroFooter.fills = [];
  heroFooter.resize(334, 32);

  const heroFooterText = figma.createText();
  heroFooterText.characters = "Denintel Koarmada 2 • Real-time Sync";
  heroFooterText.fontSize = 11;
  heroFooterText.fontName = { family: "Inter", style: "Regular" };
  heroFooterText.fills = [{ type: "SOLID", color: { r: 0.7, g: 0.78, b: 0.9 } }];
  heroFooter.appendChild(heroFooterText);
  heroCard.appendChild(heroFooter);

  frameDashboard.appendChild(heroCard);

  // Trio Metrics (Pemasukan, Pengeluaran, Kas Taktis)
  const trioContainer = figma.createFrame();
  trioContainer.name = "Financial Trio Cards";
  trioContainer.resize(382, 80);
  trioContainer.x = 24;
  trioContainer.y = 342;
  trioContainer.layoutMode = "HORIZONTAL";
  trioContainer.itemSpacing = 12;
  trioContainer.fills = [];

  const trioItems = [
    { title: "Pemasukan", val: "Rp 45.2M", bg: COLOR_TEAL_LIGHT, fg: COLOR_TEAL_DARK },
    { title: "Pengeluaran", val: "Rp 12.8M", bg: COLOR_RED_LIGHT, fg: COLOR_RED_DARK },
    { title: "Kas Teknis", val: "Rp 8.5M", bg: COLOR_BLUE_LIGHT, fg: COLOR_BLUE_DARK }
  ];

  trioItems.forEach(item => {
    const card = figma.createFrame();
    card.resize(119, 80);
    card.cornerRadius = 18;
    card.fills = [{ type: "SOLID", color: COLOR_WHITE }];
    card.effects = createShadow();
    card.paddingLeft = 12;
    card.paddingTop = 14;
    card.paddingRight = 12;
    card.paddingBottom = 14;
    card.layoutMode = "VERTICAL";
    card.itemSpacing = 4;

    const lbl = figma.createText();
    lbl.characters = item.title;
    lbl.fontSize = 11;
    lbl.fontName = { family: "Inter", style: "Medium" };
    lbl.fills = [{ type: "SOLID", color: COLOR_TEXT_MUTED }];
    card.appendChild(lbl);

    const val = figma.createText();
    val.characters = item.val;
    val.fontSize = 14;
    val.fontName = { family: "Inter", style: "Bold" };
    val.fills = [{ type: "SOLID", color: item.fg }];
    card.appendChild(val);

    trioContainer.appendChild(card);
  });
  frameDashboard.appendChild(trioContainer);

  // Quick Actions Grid (8 Modern Menu Items)
  const menuTitle = figma.createText();
  menuTitle.characters = "Layanan Kedinasan";
  menuTitle.fontSize = 16;
  menuTitle.fontName = { family: "Inter", style: "Bold" };
  menuTitle.fills = [{ type: "SOLID", color: COLOR_TEXT_MAIN }];
  menuTitle.x = 24;
  menuTitle.y = 442;
  frameDashboard.appendChild(menuTitle);

  const gridContainer = figma.createFrame();
  gridContainer.name = "Quick Actions 4x2 Grid";
  gridContainer.resize(382, 170);
  gridContainer.x = 24;
  gridContainer.y = 472;
  gridContainer.layoutMode = "VERTICAL";
  gridContainer.itemSpacing = 16;
  gridContainer.fills = [];

  const row1 = figma.createFrame();
  row1.layoutMode = "HORIZONTAL";
  row1.primaryAxisAlignItems = "SPACE_BETWEEN";
  row1.fills = [];
  row1.resize(382, 74);

  const row2 = figma.createFrame();
  row2.layoutMode = "HORIZONTAL";
  row2.primaryAxisAlignItems = "SPACE_BETWEEN";
  row2.fills = [];
  row2.resize(382, 74);

  const menus = [
    { label: "Kas Denintel", col: COLOR_TEAL_LIGHT },
    { label: "Kas Teknis", col: COLOR_BLUE_LIGHT },
    { label: "Buku Tamu", col: { r: 0.98, g: 0.95, b: 0.88 } },
    { label: "SKHPP", col: { r: 0.95, g: 0.92, b: 0.98 } },
    { label: "Surat Masuk", col: COLOR_BLUE_LIGHT },
    { label: "Disposisi", col: COLOR_TEAL_LIGHT },
    { label: "Mitra Intel", col: { r: 0.98, g: 0.93, b: 0.90 } },
    { label: "Personel", col: { r: 0.92, g: 0.96, b: 0.98 } }
  ];

  menus.slice(0, 4).forEach(m => {
    const itemFrame = figma.createFrame();
    itemFrame.layoutMode = "VERTICAL";
    itemFrame.counterAxisAlignItems = "CENTER";
    itemFrame.itemSpacing = 6;
    itemFrame.fills = [];
    itemFrame.resize(80, 74);

    const iconBox = figma.createFrame();
    iconBox.resize(48, 48);
    iconBox.cornerRadius = 16;
    iconBox.fills = [{ type: "SOLID", color: m.col }];
    itemFrame.appendChild(iconBox);

    const txt = figma.createText();
    txt.characters = m.label;
    txt.fontSize = 11;
    txt.fontName = { family: "Inter", style: "Medium" };
    txt.fills = [{ type: "SOLID", color: COLOR_TEXT_MAIN }];
    itemFrame.appendChild(txt);
    row1.appendChild(itemFrame);
  });

  menus.slice(4, 8).forEach(m => {
    const itemFrame = figma.createFrame();
    itemFrame.layoutMode = "VERTICAL";
    itemFrame.counterAxisAlignItems = "CENTER";
    itemFrame.itemSpacing = 6;
    itemFrame.fills = [];
    itemFrame.resize(80, 74);

    const iconBox = figma.createFrame();
    iconBox.resize(48, 48);
    iconBox.cornerRadius = 16;
    iconBox.fills = [{ type: "SOLID", color: m.col }];
    itemFrame.appendChild(iconBox);

    const txt = figma.createText();
    txt.characters = m.label;
    txt.fontSize = 11;
    txt.fontName = { family: "Inter", style: "Medium" };
    txt.fills = [{ type: "SOLID", color: COLOR_TEXT_MAIN }];
    itemFrame.appendChild(txt);
    row2.appendChild(itemFrame);
  });

  gridContainer.appendChild(row1);
  gridContainer.appendChild(row2);
  frameDashboard.appendChild(gridContainer);

  // Recent Activity Header & Card
  const actTitle = figma.createText();
  actTitle.characters = "Kondisi Hari Ini";
  actTitle.fontSize = 16;
  actTitle.fontName = { family: "Inter", style: "Bold" };
  actTitle.fills = [{ type: "SOLID", color: COLOR_TEXT_MAIN }];
  actTitle.x = 24;
  actTitle.y = 660;
  frameDashboard.appendChild(actTitle);

  const actCard = figma.createFrame();
  actCard.name = "Activity Card";
  actCard.resize(382, 100);
  actCard.x = 24;
  actCard.y = 690;
  actCard.cornerRadius = 20;
  actCard.fills = [{ type: "SOLID", color: COLOR_WHITE }];
  actCard.effects = createShadow();
  actCard.paddingLeft = 18;
  actCard.paddingRight = 18;
  actCard.paddingTop = 16;
  actCard.paddingBottom = 16;
  actCard.layoutMode = "HORIZONTAL";
  actCard.primaryAxisAlignItems = "SPACE_BETWEEN";
  actCard.counterAxisAlignItems = "CENTER";

  const actInfo = figma.createFrame();
  actInfo.layoutMode = "VERTICAL";
  actInfo.itemSpacing = 4;
  actInfo.fills = [];
  
  const actName = figma.createText();
  actName.characters = "Buku Tamu & Pengamanan Fisik";
  actName.fontSize = 14;
  actName.fontName = { family: "Inter", style: "Bold" };
  actName.fills = [{ type: "SOLID", color: COLOR_TEXT_MAIN }];
  actInfo.appendChild(actName);

  const actSub = figma.createText();
  actSub.characters = "14 Tamu Terverifikasi • 3 Disposisi Perlu TTD";
  actSub.fontSize = 12;
  actSub.fontName = { family: "Inter", style: "Regular" };
  actSub.fills = [{ type: "SOLID", color: COLOR_TEXT_MUTED }];
  actInfo.appendChild(actSub);
  actCard.appendChild(actInfo);

  const actBadge = figma.createFrame();
  actBadge.cornerRadius = 10;
  actBadge.fills = [{ type: "SOLID", color: COLOR_TEAL_LIGHT }];
  actBadge.paddingLeft = 10;
  actBadge.paddingRight = 10;
  actBadge.paddingTop = 6;
  actBadge.paddingBottom = 6;
  const actBadgeTxt = figma.createText();
  actBadgeTxt.characters = "Aman";
  actBadgeTxt.fontSize = 11;
  actBadgeTxt.fontName = { family: "Inter", style: "Semi Bold" };
  actBadgeTxt.fills = [{ type: "SOLID", color: COLOR_TEAL_DARK }];
  actBadge.appendChild(actBadgeTxt);
  actCard.appendChild(actBadge);

  frameDashboard.appendChild(actCard);

  // Modern Floating Bottom Pill Navigation Bar
  const bottomBar = figma.createFrame();
  bottomBar.name = "Floating Bottom Pill Nav";
  bottomBar.resize(382, 68);
  bottomBar.x = 24;
  bottomBar.y = 830;
  bottomBar.cornerRadius = 34;
  bottomBar.fills = [{ type: "SOLID", color: COLOR_WHITE }];
  bottomBar.effects = [{
    type: "DROP_SHADOW",
    visible: true,
    color: { r: 0.04, g: 0.10, b: 0.24, a: 0.12 },
    offset: { x: 0, y: 8 },
    radius: 28,
    spread: 0,
    blendMode: "NORMAL"
  }];
  bottomBar.layoutMode = "HORIZONTAL";
  bottomBar.primaryAxisAlignItems = "SPACE_EVENLY";
  bottomBar.counterAxisAlignItems = "CENTER";

  const navTabs = ["Beranda", "Kas", "Surat", "SKHPP", "Akun"];
  navTabs.forEach((tab, idx) => {
    const tabFrame = figma.createFrame();
    tabFrame.layoutMode = "VERTICAL";
    tabFrame.counterAxisAlignItems = "CENTER";
    tabFrame.itemSpacing = 2;
    tabFrame.fills = [];

    const dot = figma.createFrame();
    dot.resize(20, 20);
    dot.cornerRadius = 10;
    dot.fills = [{ type: "SOLID", color: idx === 0 ? COLOR_NAVY_LIGHT : { r: 0.8, g: 0.85, b: 0.9 } }];
    tabFrame.appendChild(dot);

    const txt = figma.createText();
    txt.characters = tab;
    txt.fontSize = 10;
    txt.fontName = { family: "Inter", style: idx === 0 ? "Bold" : "Medium" };
    txt.fills = [{ type: "SOLID", color: idx === 0 ? COLOR_NAVY_LIGHT : COLOR_TEXT_MUTED }];
    tabFrame.appendChild(txt);

    bottomBar.appendChild(tabFrame);
  });
  frameDashboard.appendChild(bottomBar);

  // Add Frame to canvas
  page.appendChild(frameDashboard);
  figma.viewport.scrollAndZoomIntoView([frameDashboard]);
  figma.notify("Desain SISINDEN Berhasil Dibuat di Kanvas Figma!");
  figma.closePlugin();
}

run();
`;

fs.writeFileSync(path.join(outDir, 'code.js'), codeJs, 'utf8');

console.log("Figma plugin files created successfully in:", outDir);
