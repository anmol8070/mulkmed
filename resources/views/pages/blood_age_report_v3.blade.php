<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Deep Longevity Blood Age Report</title>

<style>

:root {
    --primary-orange: #e65c2b;
    --primary-green: #22c55e;

    --text-dark: #1a1a1a;
    --text-gray: #444444;
    --border-color: #e5e7eb;

    --card-bg: #f8f8fa;
    --card-purple: #e8e4fc;
    --card-yellow: #fef3c7;
    --card-turquoise: #ccfbf1;

    --footer-bg: #e8ebfc;
    --footer-layer-4: #9baaf7;
    --footer-layer-3: #b0bcf9;
    --footer-layer-2: #c8d0fa;
    --footer-layer-1: #e8ebfc;

    /* Even page margins on all sides (screen + print) */
    --page-margin: 28px;
    --footer-height: 80px;

    --gradient-pink-purple:
        linear-gradient(
            90deg,
            #f472b6 0%,
            #c084fc 50%,
            #a78bfa 100%
        );
}


/* =========================================================
   GLOBAL
========================================================= */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 40px 0;
    background: #e5e7eb;

    font-family:
        'Inter',
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Roboto,
        Helvetica,
        Arial,
        sans-serif;

    color: var(--text-dark);
}


/* =========================================================
   A4 PAGE
========================================================= */

.page-container {
    width: 1000px;
    height: 1414px;
    min-height: 1414px;
    max-height: 1414px;

    margin: 0 auto 40px auto;

    background: #e8ebfc;

    position: relative;

    overflow: hidden;

    box-shadow: 0 10px 30px rgba(0,0,0,0.10);

    page-break-after: always;
    page-break-inside: avoid;

    display: flex;
    flex-direction: column;
}


/* =========================================================
   HEADER
========================================================= */

.header {
    width: 100%;
    padding: var(--page-margin) var(--page-margin) 10px var(--page-margin);
    background: linear-gradient(180deg, #f0eefc 0%, #e8ebfc 100%);
    flex: 0 0 auto;
}

table.header-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

table.header-table td {
    width: 33.33%;
    vertical-align: middle;
    text-align: center;
    padding: 0 6px;
}

table.header-table td.left {
    text-align: left;
}

table.header-table td.right {
    text-align: right;
}

.logo-container {
    display: inline-block;
    vertical-align: middle;
    margin-bottom: 2px;
}

.logo-container svg {
    width: 32px !important;
    height: 32px !important;
}

.header-company-logo {
    display: block;
    width: auto;
    height: 52px;
    max-width: 140px;
    object-fit: contain;
}

.header-report-logo {
    display: block;
    width: 70px;
    height: 70px;
    margin: 0 auto;
    object-fit: contain;
    border-radius: 50%;
}

.logo-text {
    display: inline-block;
    vertical-align: middle;
    font-weight: 800;
    font-size: 16px;
    line-height: 1.05;
    color: #333;
    letter-spacing: 0.4px;
    margin-left: 8px;
    text-align: left;
}

.header-title {
    margin: 2px 0 0 0;
    font-size: 12px;
    font-weight: 700;
    color: #1a1a1a;
}

.header-table svg {
    max-width: 52px;
    max-height: 52px;
}

.header-table img {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}


/* =========================================================
   PAGE CONTENT
========================================================= */

.page-content {
    padding: 12px var(--page-margin) 24px var(--page-margin);
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    position: relative;
    background: #e8ebfc;
    min-height: 0;
    justify-content: flex-start;
    gap: 10px;
    box-sizing: border-box;
    /* Keep overflowing content inside the content area — never under the footer */
    overflow: hidden;
}

/* Spacer must not create an empty band above the footer */
.page-spacer {
    display: none !important;
    flex: 0 0 0 !important;
    height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
}

/*
 * Fill leftover A4 height with the final content block so the
 * footer sits ~24px below content (padding-bottom), not mid-page.
 * Does not stretch text; section containers absorb the height.
 */
.page-content > :last-child:not(.page-spacer):not(.calculation-card):not(.biomarker-section-card):not(.warning-container):not(.organ-unused-note):not(.organ-card),
.page-content > :has(+ .page-spacer):not(.calculation-card):not(.biomarker-section-card):not(.warning-container):not(.organ-unused-note):not(.organ-card) {
    flex: 1 1 auto;
    min-height: 0;
}

/* Content cards must stay content-height — never absorb leftover page height */
.page-content > .calculation-card,
.page-content > .biomarker-section-card,
.page-content > .warning-container,
.page-content > .organ-card.organ-unused-note,
.page-content > .organ-card {
    flex: 0 0 auto;
    flex-grow: 0;
    height: auto;
    min-height: 0;
}

/*
 * Pages 4–6 ONLY (Blood Age Calculation):
 * Keep each calculation-card as one unbreakable unit.
 * Content stays full size — never shrink/split/clip under the footer.
 * Footer reserved via footer-slot; ~4px gap above footer.
 */
.page-container.page-calc-safe {
    padding-bottom: 0;
    box-sizing: border-box;
}

.page-container.page-calc-safe > .footer-slot {
    display: block;
    visibility: hidden;
    pointer-events: none;
    flex: 0 0 calc(var(--footer-height) + 4px);
    width: 100%;
    height: calc(var(--footer-height) + 4px);
    max-height: calc(var(--footer-height) + 4px);
    margin: 0;
    padding: 0;
    overflow: hidden;
}

.page-container.page-calc-safe > .page-content {
    flex: 1 1 0%;
    min-height: 0;
    height: auto;
    max-height: none;
    box-sizing: border-box;
    justify-content: flex-start;
    align-content: flex-start;
    padding-bottom: 0;
    overflow: visible;
}

.page-container.page-calc-safe > .page-content > :last-child {
    margin-bottom: 0;
}

.page-container.page-calc-safe > .page-content > .calculation-card {
    flex: 0 0 auto;
    flex-grow: 0;
    flex-shrink: 0;
    height: auto;
    min-height: 0;
    max-height: none;
    margin-bottom: 0;
    break-inside: avoid;
    page-break-inside: avoid;
}

.page-container.page-calc-safe > .footer {
    position: absolute;
    left: 0;
    bottom: 0;
    width: 100%;
    height: var(--footer-height);
}


/* =========================================================
   SECTION TITLE
========================================================= */

.section-title-container {
    display: flex;
    align-items: center;
    position: relative;
    margin-left: calc(-1 * var(--page-margin));
    padding-left: var(--page-margin);
    margin-bottom: 0;
    flex: 0 0 auto;
}


.title-bar {
    height: 40px;
    width: 520px;
    background: var(--gradient-pink-purple);
    border-radius: 0 20px 20px 0;
    display: flex;
    align-items: center;
    padding-left: 72px;
    box-shadow: 0 3px 8px rgba(192,132,252,0.18);
}


.title-bar h2 {
    margin: 0;
    color: #ffffff;
    font-size: 18px;
    font-weight: 700;
}


.title-icon {
    width: 56px;
    height: 56px;
    background: #ffffff;
    border-radius: 50%;
    position: absolute;
    left: var(--page-margin);
    top: 50%;
    transform: translateY(-50%);
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
}

.title-icon svg {
    width: 26px !important;
    height: 26px !important;
}

.title-icon img {
    width: 34px;
    height: 34px;
    object-fit: contain;
    display: block;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}


/* =========================================================
   PAGE 1
   PATIENT INFORMATION
========================================================= */

table.patient-info-bar {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 0;
    font-size: 13px;
    background: #ffffff;
    flex: 0 0 auto;
}

table.patient-info-bar td {
    width: 25%;
    padding: 12px 12px;
    vertical-align: middle;
    text-align: left;
}

table.patient-info-bar span {
    font-weight: 700;
}


/* =========================================================
   PAGE 1
   HERO
========================================================= */

table.hero-section {
    width: 100%;
    border-collapse: collapse;
    background: #f8f8fa;
    border-radius: 10px;
    margin-bottom: 0;
    flex: 1 1 auto;
}

table.hero-section td {
    vertical-align: middle;
    padding: 16px 14px;
}

table.hero-section td.illust {
    width: 32%;
    text-align: center;
}

table.hero-section td.illust svg {
    width: 130px !important;
    height: 88px !important;
}

table.hero-section td.illust .hero-pace-clock {
    display: block;
    width: 200px;
    height: auto;
    max-width: 100%;
    margin: 0 auto;
    object-fit: contain;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.page1-card-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 8px 0;
}

.page1-card-title .card-title {
    margin: 0;
}

.page1-title-icon {
    width: 22px;
    height: 22px;
    object-fit: contain;
    flex-shrink: 0;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.page1-inline-icon {
    width: 16px;
    height: 16px;
    vertical-align: -3px;
    margin: 0 2px;
    object-fit: contain;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

table.insights-grid .insight-heading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 0 0 6px 0;
}

table.insights-grid .insight-icon {
    display: block;
    width: 40px;
    height: 32px;
    margin: 0;
    flex-shrink: 0;
    object-fit: contain;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

table.insights-grid .insight-heading h3 {
    margin: 0;
    text-align: left;
    white-space: nowrap;
}

table.insights-grid td {
    text-align: center;
}

table.insights-grid p {
    text-align: center;
}

table.hero-section td.ages {
    width: 68%;
    text-align: center;
}

table.age-comparison {
    width: 100%;
    border-collapse: collapse;
    margin: 0 auto 6px auto;
}

table.age-comparison td {
    vertical-align: middle;
    text-align: center;
    padding: 4px 10px;
}

.age-label {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 4px;
}

.age-value {
    font-size: 42px;
    font-weight: 800;
    line-height: 1;
    color: #1a1a1a;
}

.age-value.green {
    color: #22c55e;
}

.hero-footer-text {
    width: 92%;
    margin: 8px auto 0 auto;
    padding-top: 10px;
    border-top: 1px solid #e5e7eb;
    text-align: center;
    font-size: 13px;
    font-weight: 500;
}


/* =========================================================
   PAGE 1
   INFORMATION CARDS
========================================================= */

table.info-grid {
    width: 100%;
    border-collapse: separate;
    border-spacing: 10px 0;
    margin: 0;
    table-layout: fixed;
    flex: 1 1 auto;
}

table.info-grid td {
    width: 50%;
    vertical-align: top;
    background: #f8f8fa;
    border-radius: 10px;
    padding: 14px 14px;
}

.card-title {
    margin: 0 0 8px 0;
    font-size: 13px;
    font-weight: 800;
    color: #1a1a1a;
}

.card-text {
    margin: 0;
    font-size: 12px;
    line-height: 1.45;
    color: #444444;
}

.card-text strong {
    color: #1a1a1a;
}


/* =========================================================
   PAGE 1
   COMPARE SECTION
========================================================= */

table.compare-section {
    width: 100%;
    border-collapse: collapse;
    background: #f8f8fa;
    border-radius: 10px;
    margin-bottom: 0;
    flex: 1 1 auto;
}

table.compare-section td {
    vertical-align: middle;
    padding: 14px 14px;
}

table.compare-section td.copy {
    width: 55%;
}

table.compare-section td.gauge {
    width: 45%;
    text-align: center;
}

.compare-content h2 {
    margin: 0 0 6px 0;
    font-size: 15px;
    font-weight: 800;
}

.compare-content p {
    margin: 0;
    font-size: 11px;
    line-height: 1.4;
    color: #444444;
}

.gauge-label {
    font-weight: 800;
    color: #7c3aed;
    font-size: 13px;
    margin-top: 2px;
    text-align: center;
}


.gauge-container {
    width: 170px;
    height: 95px;
    margin: 0 auto;
}

.gauge-container svg {
    width: 170px !important;
    height: 95px !important;
}


/* =========================================================
   PAGE 1
   QUICK INSIGHTS
========================================================= */

.insights-title {
    text-align: center;
    font-size: 16px;
    font-weight: 800;
    margin: 0;
    flex: 0 0 auto;
}

table.insights-grid {
    width: 100%;
    border-collapse: separate;
    border-spacing: 8px 0;
    table-layout: fixed;
    margin-bottom: 0;
    flex: 1 1 auto;
}

table.insights-grid td {
    width: 25%;
    vertical-align: top;
    background: #f8f8fa;
    border-radius: 10px;
    padding: 12px 10px;
}

.insight-card h3,
.insight-header h3,
table.insights-grid h3 {
    margin: 0;
    font-size: 11px;
    font-weight: 800;
}

.insight-card p,
table.insights-grid p {
    margin: 0;
    font-size: 10px;
    line-height: 1.35;
    color: #444;
}

.insight-header {
    margin-bottom: 4px;
}

.insight-card p strong,
table.insights-grid p strong {
    color: #1a1a1a;
    font-size: 12px;
}


/* =========================================================
   PAGE 2
   INTRO TEXT
========================================================= */

.intro-text {
    width: 100%;
    max-width: 900px;
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
    color: #333;
    flex: 0 0 auto;
}


/* =========================================================
   PAGE 2
   THREE INFORMATION CARDS
========================================================= */

.cards-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 0;
    flex: 1 1 auto;
    align-content: stretch;
}


.cards-grid .info-card {
    background: #f8f8fa;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 14px 14px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.02);
    height: 100%;
}


.card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}


.card-header svg {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

.card-header img {
    width: 22px;
    height: 22px;
    flex-shrink: 0;
    object-fit: contain;
    display: block;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}


.card-header h3 {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
    color: #1a1a1a;
}


.cards-grid .info-card p {
    margin: 0;
    font-size: 11px;
    line-height: 1.4;
    color: #333;
}


/* =========================================================
   PAGE 2
   PROCESS CARDS
========================================================= */

.process-card {
    width: 100%;
    min-height: 0;
    height: auto;
    display: flex;
    overflow: hidden;
    background: #f8f8fa;
    border-radius: 8px;
    margin-bottom: 0;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    flex: 1 1 auto;
}


.process-number-block {
    width: 100px;
    flex: 0 0 100px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    padding: 14px 8px;
}


.process-number-block .number {
    font-size: 32px;
    font-weight: 800;
    line-height: 1;
    color: #1a1a1a;
    margin-bottom: 2px;
}


.process-number-block .label {
    font-size: 12px;
    font-weight: 700;
    color: #1a1a1a;
}


.process-content {
    flex: 1;
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}


.process-content h3 {
    margin: 0 0 6px 0;
    font-size: 14px;
    font-weight: 700;
    color: #1a1a1a;
}


.process-content p {
    margin: 0;
    font-size: 12px;
    line-height: 1.45;
    color: #333;
}


.bg-purple {
    background: var(--card-purple);
}


.bg-yellow {
    background: var(--card-yellow);
}


.bg-turquoise {
    background: var(--card-turquoise);
}


/* =========================================================
   FOOTER  (match reference screenshot exactly)
========================================================= */

/*
 * Reserves the footer’s physical height in the page flex flow so
 * .page-content can never extend into the footer safe area.
 * The visible footer is absolutely pinned to the page bottom.
 */
.footer-slot {
    flex: 0 0 var(--footer-height);
    width: 100%;
    height: var(--footer-height);
    max-height: var(--footer-height);
    margin: 0;
    padding: 0;
    pointer-events: none;
    visibility: hidden;
    overflow: hidden;
}

.footer {
    position: absolute;
    left: 0;
    bottom: 0;
    width: 100%;
    max-width: 100%;
    height: var(--footer-height);
    margin: 0;
    padding: 0;
    background: #ffffff;
    box-shadow: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    overflow: hidden;
    z-index: 10;
    border-top: none;
    box-sizing: border-box;
}

.footer *,
.footer *::before,
.footer *::after {
    box-sizing: border-box;
}

.footer-bg-bar {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: #ffffff;
    z-index: 1;
    pointer-events: none;
}

.footer-shapes {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 2;
    overflow: hidden;
    pointer-events: none;
}

.footer .shape-layer {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    border-radius: 0 40px 40px 0;
}

/* Widths match reference ratios (800 / 725 / 635 / 520 on ~1292px ≈ 62% / 56% / 49% / 40%) */
.footer .layer-4 {
    width: 62%;
    max-width: 100%;
    background: #9baaf7;
    z-index: 1;
}

.footer .layer-3 {
    width: 56.1%;
    max-width: 100%;
    background: #b0bcf9;
    z-index: 2;
}

.footer .layer-2 {
    width: 49.1%;
    max-width: 100%;
    background: #c8d0fa;
    z-index: 3;
}

.footer .layer-1 {
    width: 40.2%;
    max-width: 100%;
    background: #e8ebfc;
    z-index: 4;
}

.footer-content-left {
    position: relative;
    z-index: 5;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: flex-start;
    gap: 4px;
    padding-left: 8px;
    padding-right: 16px;
    margin-left: 0;
    margin-right: auto;
    min-width: 0;
    max-width: none;
    text-align: left;
    flex: 0 0 auto;
}

.footer-logo {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 8px;
    margin: 0;
    text-align: left;
}

.footer-logo-svg {
    width: 24px;
    height: 24px;
    flex-shrink: 0;
}

.footer-company-logo {
    display: block;
    width: auto;
    height: 40px;
    max-width: 120px;
    object-fit: contain;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.footer-logo-text {
    font-weight: 800;
    font-size: 13px;
    line-height: 1.1;
    color: #222;
    letter-spacing: 0.5px;
    text-align: left;
}

.footer-copyright {
    margin: 0;
    padding: 0;
    font-size: 13px;
    font-weight: 400;
    line-height: 1.2;
    color: #333;
    white-space: nowrap;
    text-align: left;
}

.footer .page-number {
    position: relative;
    z-index: 5;
    margin: 0;
    margin-left: auto;
    padding-right: 70px;
    font-size: 17px;
    font-weight: 500;
    line-height: 1.2;
    color: #222;
    white-space: nowrap;
    flex-shrink: 0;
    text-align: right;
}


/* =========================================================
   PAGES 3-17 SHARED COMPONENTS
========================================================= */

/* Full-width light lavender card used on Pages 3, 13, 14, 15, 16, 17 */
.biomarker-section-card,
.calculation-card,
.warning-container {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 16px 16px;
    margin-bottom: 10px;
    flex: 0 0 auto;
}

.biomarker-section-card {
    background: #F8F7FC;
    border: 1px solid #D8D8E3;
    border-radius: 12px;
    padding: 14px 14px 12px;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.biomarker-section-title,
.calculation-card .card-title {
    margin: 0 0 8px 0;
    font-size: 14px;
    font-weight: 800;
    color: var(--text-dark);
}

.biomarker-section-title {
    margin: 0 0 10px 0;
    font-size: 15px;
    font-weight: 800;
    line-height: 1.2;
}

/* Biomarker two column grid */
.biomarker-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.biomarker-item {
    background: #ffffff;
    border: 1px solid #E8E8EE;
    border-radius: 12px;
    padding: 6px 6px 6px 0;
    display: flex;
    align-items: stretch;
    overflow: hidden;
    min-height: 72px;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.biomarker-info {
    flex: 1;
    min-width: 0;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.biomarker-name-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
    flex-wrap: wrap;
}

.biomarker-name-row h4 {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
    color: var(--text-dark);
    line-height: 1.2;
}

.status-pill {
    font-size: 10px;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 999px;
    white-space: nowrap;
    line-height: 1.3;
}

.status-pill.green {
    background: #EEFDF3;
    color: #117B34;
}

.status-pill.red {
    background: #FDF2F2;
    color: #DE3B40;
}

.status-pill.gray {
    background: #F3F4F6;
    color: #323842;
}

.biomarker-meta {
    font-size: 10px;
    color: #9095A0;
    display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 14px;
    width: 100%;
    flex-wrap: nowrap;
}

/* When value is Not Available, the ref/unit text should be darker (as per screenshot). */
.biomarker-item:has(.status-pill.gray) .biomarker-meta {
    color: #323842;
}

.biomarker-meta span {
    flex: 0 0 auto;
    white-space: nowrap;
}

.value-box {
    width: 68px;
    min-width: 68px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 16px;
    font-weight: 700;
    color: #1a1a1a;
    border-radius: 10px;
    flex-shrink: 0;
    align-self: stretch;
    padding: 8px 6px;
    line-height: 1.1;
    text-align: center;
    word-break: break-word;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.value-box.green { background: #B8F5CD; color: #1a1a1a; }

.value-box.red { background: #F8CEDB; color: #1a1a1a; }

.value-box.gray  { background: #DEE1E6; color: #323842; }

/* Aging scale — right half of card (Figma) */
.calculation-card {
    display: grid;
    grid-template-columns: 1fr 1fr;
    align-items: start;
    column-gap: 12px;
}

.calculation-card .card-title {
    grid-column: 1;
    margin: 0;
    align-self: center;
}

.aging-scale {
    grid-column: 2;
    width: 100%;
    margin: 0 0 8px 0;
}

.aging-scale-header {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    align-items: end;
    width: 100%;
    margin: 0 0 4px 0;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.2;
    white-space: nowrap;
}

.aging-scale-header .anti-aging {
    color: #16a34a;
    text-align: left;
    justify-self: start;
}

.aging-scale-header .impact-years {
    color: #1a1a1a;
    text-align: center;
    justify-self: center;
}

.aging-scale-header .aging {
    color: #e11d48;
    text-align: right;
    justify-self: end;
}

.aging-scale-bar {
    display: flex;
    width: 100%;
    height: 14px;
    overflow: hidden;
    border-radius: 7px;
}

.aging-scale-bar .anti-aging-bar {
    width: 50%;
    background: #bbf7d0;
    border-radius: 7px 0 0 7px;
}

.aging-scale-bar .aging-bar {
    width: 50%;
    background: #fecaca;
    border-radius: 0 7px 7px 0;
    border-left: 2px solid #9ca3af;
    box-sizing: border-box;
}

/* Value rows — name left; values align to green / center / pink under the scale */
.impact-data-row {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: 1fr 1fr;
    align-items: center;
    column-gap: 12px;
    min-height: 34px;
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 4px 10px;
    margin-bottom: 6px;
    box-sizing: border-box;
}

.impact-data-row:last-child { margin-bottom: 0; }

.impact-row-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dark);
    line-height: 1.25;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    min-width: 0;
    justify-self: start;
}

/* Right column matches aging-scale width — green left | pink right */
.impact-value-track {
    display: grid;
    grid-template-columns: 1fr 1fr;
    align-items: center;
    width: 100%;
    min-height: 28px;
    box-sizing: border-box;
}

.impact-value-track.align-empty {
    min-height: 28px;
}

/* Anti-aging (green) → left half under green scale */
.impact-value-track.align-anti .value-pill {
    grid-column: 1;
    justify-self: start;
}

/* Neutral (0) → exact center under Impact (Years) */
.impact-value-track.align-neutral {
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Aging (red) → pink bar on right half, value at start of bar */
.impact-value-track.align-aging .value-pill {
    grid-column: 2;
    justify-self: stretch;
    text-align: left;
    width: 100%;
    box-sizing: border-box;
}

.value-pill {
    font-size: 12px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 6px;
    min-width: 48px;
    text-align: center;
    display: inline-block;
    line-height: 1.2;
    box-sizing: border-box;
}
.value-pill.green { color: #15803d; background: #d1fae5; }
.value-pill.red   { color: #b91c1c; background: #fecaca; }
.value-pill.neutral { color: #4b5563; background: #e5e7eb; }

.not-available-pill {
    font-size: 9px;
    font-weight: 500;
    color: #4b5563;
    background: #e5e7eb;
    padding: 1px 7px;
    border-radius: 10px;
}

/* Generic biomarker row used on Page 3 (percentage list) */
.biomarker-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 34px;
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 0 10px;
    margin-bottom: 6px;
}

.biomarker-row:last-child { margin-bottom: 0; }

.biomarker-row .biomarker-name {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Simple two column info row */
.info-card-row {
    display: flex;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 10px;
}

.info-card-icon {
    width: 120px;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-shrink: 0;
}

.info-card-icon.green { background: #d1fae5; }
.info-card-icon.yellow { background: #fef3c7; }
.info-card-icon.red { background: #fee2e2; }

.info-card-icon svg { width: 44px !important; height: 44px !important; }

.info-card-icon img {
    width: 56px;
    height: 56px;
    object-fit: contain;
    display: block;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.info-card-content {
    flex: 1;
    padding: 12px 16px;
}

.info-card-content h3 {
    margin: 0 0 6px 0;
    font-size: 14px;
    font-weight: 700;
    color: var(--text-dark);
}

.info-card-content p {
    margin: 0;
    font-size: 12px;
    line-height: 1.5;
    color: #333;
}

.info-card-pill {
    display: inline-block;
    background: #d1fae5;
    color: #166534;
    padding: 4px 12px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 12px;
    margin-bottom: 8px;
}

/* Page 3 specific styles */
.intro-text-block {
    margin-bottom: 10px;
    padding-left: 4px;
}

.intro-text-block p {
    font-size: 13px;
    font-weight: 700;
    line-height: 1.45;
    color: #1a1a1a;
    margin: 0 0 4px 0;
}

.intro-text-block ul {
    margin: 0;
    padding-left: 16px;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.45;
    color: #1a1a1a;
}

.biomarker-headers {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    gap: 16px;
}

.header-left-group, .header-right-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.header-pill {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 36px;
    border-radius: 20px;
    color: #ffffff;
    font-weight: 700;
    font-size: 13px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    width: 260px;
}

.header-pill.green { background: var(--primary-green); }
.header-pill.red { background: #dc2626; }

.header-btn {
    width: 44px;
    height: 36px;
    background: #ffffff;
    border-radius: 18px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 20px;
    font-weight: 700;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    cursor: pointer;
}

.header-btn.green-btn {
    border: 2px solid var(--primary-green);
    color: var(--primary-green);
}

.header-btn.red-btn {
    border: 2px solid #dc2626;
    color: #dc2626;
}

.biomarker-section {
    display: flex;
    justify-content: space-between;
    flex: 0 0 auto;
    position: relative;
    padding-bottom: 20px;
}

.biomarker-column {
    width: 44%;
    border-radius: 10px;
    padding: 10px;
    background: #ffffff;
    box-sizing: border-box;
}

.biomarker-column.left { border: 2px solid var(--primary-green); }
.biomarker-column.right { border: 2px solid #f43f5e; }

.biomarker-column .biomarker-row {
    height: 28px;
    margin-bottom: 4px;
}

.biomarker-column.left .biomarker-row {
    background: #e8fbe8;
    border: 1px solid var(--primary-green);
}

.biomarker-column.right .biomarker-row {
    background: #fdecec;
    border: 1px solid #f43f5e;
}

.biomarker-column .biomarker-name {
    font-size: 11px;
}

.biomarker-percentage-pill {
    width: 44px;
    height: 20px;
    border-radius: 10px;
    display: flex;
    justify-content: center;
    align-items: center;
    color: #ffffff;
    font-weight: 700;
    font-size: 10px;
    flex-shrink: 0;
}

.biomarker-percentage-pill.green { background: var(--primary-green); }
.biomarker-percentage-pill.red { background: #dc2626; }

.center-indicator {
    position: absolute;
    left: 50%;
    top: 40%;
    transform: translate(-50%, -50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    z-index: 5;
}

.center-indicator svg {
    width: 32px;
    height: 32px;
    margin-right: -8px;
    z-index: 2;
}

.center-indicator .biomarker-runner {
    display: block;
    width: 84px;
    height: auto;
    object-fit: contain;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.indicator-pill {
    background: var(--primary-green);
    color: #ffffff;
    font-weight: 700;
    font-size: 12px;
    height: 32px;
    width: 60px;
    padding-left: 0;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

/* Page 7 specific styles */
.calc-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.calc-row {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    font-weight: 700;
    flex-wrap: wrap;
    justify-content: center;
}

.calc-badge {
    padding: 5px 12px;
    border-radius: 6px;
    color: #ffffff;
    font-weight: 700;
    font-size: 14px;
}

.calc-badge.red { background: #dc2626; }
.calc-badge.green { background: #16a34a; }
.calc-badge.pink { background: #ec4899; }

.calc-marker {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    color: #ffffff;
    border-radius: 50% 50% 50% 0;
    transform: rotate(-45deg);
}

.calc-marker.black { background: #1a1a1a; }
.calc-marker.red { background: #dc2626; }

.calc-marker span { transform: rotate(45deg); }

/* Pages 8-9 specific styles */
.score-legend {
    background: #F5F5F7;
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    padding: 12px 16px 14px;
    margin-bottom: 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.score-legend > .score-legend-title {
    font-size: 13px;
    font-weight: 700;
    color: #171A1F;
    line-height: 1.2;
}

.score-legend-items {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    width: 100%;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 500;
    color: #171A1F;
    white-space: nowrap;
}

.legend-color {
    width: 48px;
    height: 12px;
    border-radius: 999px;
    flex-shrink: 0;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.legend-color.good { background: #79AC78; }
.legend-color.moderate { background: #FCD34D; }
.legend-color.poor { background: #BD574E; }

.legend-triangle {
    width: 0;
    height: 0;
    border-left: 7px solid transparent;
    border-right: 7px solid transparent;
    border-top: 11px solid #4F6BED;
    flex-shrink: 0;
}

.organ-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 10px;
}

.organ-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.organ-title-group {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 700;
}

.organ-title-group img {
    width: 22px;
    height: 22px;
    object-fit: contain;
    flex-shrink: 0;
    display: block;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.organ-score {
    font-size: 14px;
    font-weight: 800;
}

.organ-bar-container {
    height: 12px;
    background: #6E7787;
    border-radius: 6px;
    position: relative;
    margin-bottom: 12px;
    overflow: visible;
}

.organ-bar-fill {
    height: 100%;
    border-radius: 6px;
}

.organ-baseline {
    position: absolute;
    top: -6px;
    width: 0;
    height: 0;
    border-left: 6px solid transparent;
    border-right: 6px solid transparent;
    border-top: 9px solid #4F6BED;
}

.organ-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.organ-info-box {
    background: #ffffff;
    border-radius: 6px;
    padding: 10px;
    border: 1px solid #e5e7eb;
}

.organ-info-box h4 {
    margin: 0 0 4px 0;
    font-size: 11px;
    font-weight: 700;
}

.organ-info-box p {
    margin: 0;
    font-size: 10px;
    line-height: 1.4;
    color: #333;
}

.organ-card.organ-unused-note {
    padding: 10px 12px;
    margin-top: 2px;
    margin-bottom: 0;
    font-weight: 700;
    font-size: 12px;
    color: #171A1F;
    line-height: 1.3;
    flex: 0 0 auto !important;
    flex-grow: 0 !important;
    flex-shrink: 0 !important;
    height: auto !important;
    min-height: 36px !important;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

/*
 * Organ Health pages (9–10): reserve footer space and avoid clipping
 * the bottom "*Unused / Unavailable Biomarkers" card in Chrome PDF.
 */
.page-container.page-organ-health {
    padding-bottom: 0;
    box-sizing: border-box;
}

.page-container.page-organ-health > .footer-slot {
    display: block;
    visibility: hidden;
    pointer-events: none;
    flex: 0 0 calc(var(--footer-height) + 4px);
    width: 100%;
    height: calc(var(--footer-height) + 4px);
    max-height: calc(var(--footer-height) + 4px);
    margin: 0;
    padding: 0;
    overflow: hidden;
}

.page-container.page-organ-health > .page-content {
    flex: 1 1 0%;
    min-height: 0;
    height: auto;
    max-height: none;
    box-sizing: border-box;
    justify-content: flex-start;
    align-content: flex-start;
    padding-bottom: 0;
    overflow: visible;
    gap: 8px;
}

.page-container.page-organ-health > .page-content > .organ-card.organ-unused-note {
    margin-bottom: 0;
}

.page-container.page-organ-health > .footer {
    position: absolute;
    left: 0;
    bottom: 0;
    width: 100%;
    height: var(--footer-height);
}

.page-container.page-organ-health .organ-card:not(.organ-unused-note) {
    margin-bottom: 8px;
}

.page-container.page-organ-health .organ-info-box {
    padding: 8px;
}

.page-container.page-organ-health .organ-info-box p {
    font-size: 9.5px;
    line-height: 1.35;
}

/* Page 10 specific styles */
.diet-table {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 0;
}

.diet-header {
    display: grid;
    grid-template-columns: 100px 1fr 1fr 1fr;
    gap: 6px;
    font-size: 13px;
    font-weight: 700;
    flex: 0 0 auto;
}

.diet-header-cell {
    padding: 8px;
    text-align: center;
    border-radius: 6px;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.diet-header-cell.day { background: #F9F4F1; }
.diet-header-cell.breakfast { background: #CFEA9A; }
.diet-header-cell.lunch { background: #FFD88A; }
.diet-header-cell.dinner { background: #E1E3E8; }

.diet-row {
    display: grid;
    grid-template-columns: 100px 1fr 1fr 1fr;
    gap: 6px;
    flex: 1 1 auto;
    min-height: 0;
    align-items: stretch;
}

.diet-cell {
    padding: 8px 10px;
    border-radius: 6px;
    font-size: 10px;
    line-height: 1.35;
    border: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.diet-cell.day {
    font-weight: 700;
    font-size: 12px;
    text-align: center;
    background: #F9F4F1;
    justify-content: center;
}

.diet-row .diet-cell:nth-child(2) { background: #CFEA9A; }
.diet-row .diet-cell:nth-child(3) { background: #FFD88A; }
.diet-row .diet-cell:nth-child(4) { background: #E1E3E8; }

/* =========================================================
   PAGE — Weekly Exercise Plan ONLY (SVG chevron rows)
   Scoped to .page-exercise-plan — do not affect other pages
========================================================= */

.page-container.page-exercise-plan > .page-content {
    gap: 10px;
    justify-content: flex-start;
    padding-bottom: 0;
    overflow: hidden;
}

.page-container.page-exercise-plan > .footer-slot {
    flex: 0 0 calc(var(--footer-height) + 4px);
    height: calc(var(--footer-height) + 4px);
    max-height: calc(var(--footer-height) + 4px);
}

.page-container.page-exercise-plan .section-title-container {
    margin-bottom: 2px;
}

.page-container.page-exercise-plan .title-bar {
    width: 520px;
    height: 40px;
}

.page-container.page-exercise-plan .title-icon img {
    width: 34px;
    height: 34px;
    object-fit: contain;
    display: block;
}

.page-container.page-exercise-plan .wep-list {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
    gap: 11px;
    width: 100%;
}

.page-container.page-exercise-plan .wep-row {
    position: relative;
    height: 104px;
    flex: 0 0 104px;
    isolation: isolate;
    width: 100%;
}

.page-container.page-exercise-plan .wep-frame {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    overflow: visible;
    z-index: 1;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.page-container.page-exercise-plan .wep-grid {
    position: relative;
    z-index: 2;
    height: 100%;
    display: grid;
    grid-template-columns: 24% 26% 50%;
}

.page-container.page-exercise-plan .wep-col-day,
.page-container.page-exercise-plan .wep-col-act,
.page-container.page-exercise-plan .wep-col-rec {
    min-width: 0;
    height: 100%;
}

.page-container.page-exercise-plan .wep-col-day {
    display: flex;
    flex-direction: column;
    padding: 10px 26px 8px 14px;
}

.page-container.page-exercise-plan .wep-col-act {
    display: grid;
    grid-template-rows: 28% 1fr;
    padding-left: 12px;
    padding-right: 30px;
}

.page-container.page-exercise-plan .wep-act-body {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 6px 0 10px;
}

.page-container.page-exercise-plan .wep-sets,
.page-container.page-exercise-plan .wep-dur {
    padding-left: 20px;
}

.page-container.page-exercise-plan .wep-col-rec {
    display: grid;
    grid-template-rows: 28% 1fr;
    padding: 0 40px 10px 6px;
}

.page-container.page-exercise-plan .wep-day-name,
.page-container.page-exercise-plan .wep-act-name,
.page-container.page-exercise-plan .wep-rec-title {
    font-size: 13px;
    font-weight: 700;
    color: #2a2e34;
    line-height: 1.2;
    white-space: nowrap;
    margin: 0;
}

.page-container.page-exercise-plan .wep-act-name,
.page-container.page-exercise-plan .wep-rec-title {
    display: flex;
    align-items: center;
    height: 100%;
    padding: 4px 0 2px;
    box-sizing: border-box;
}

.page-container.page-exercise-plan .wep-icon {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 0;
}

.page-container.page-exercise-plan .wep-icon img {
    display: block;
    width: 58px;
    height: 58px;
    object-fit: contain;
}

.page-container.page-exercise-plan .wep-act-name,
.page-container.page-exercise-plan .wep-sets,
.page-container.page-exercise-plan .wep-dur {
    display: flex;
    align-items: center;
    white-space: nowrap;
}

.page-container.page-exercise-plan .wep-sets,
.page-container.page-exercise-plan .wep-dur {
    font-size: 12.5px;
    font-weight: 700;
    color: #2a2e34;
    line-height: 1.4;
    margin: 0;
}

.page-container.page-exercise-plan .wep-rec-text {
    font-size: 12.5px;
    font-weight: 500;
    color: #3d434b;
    line-height: 1.4;
    padding-top: 12px;
    max-width: 340px;
    margin: 0;
}

.page-container.page-exercise-plan .theme-mon { --fill:#cfe0f2; --stroke:#9eb6cc; }
.page-container.page-exercise-plan .theme-tue { --fill:#cfe9dc; --stroke:#9cc4b0; }
.page-container.page-exercise-plan .theme-wed { --fill:#e6daf2; --stroke:#c0aed4; }
.page-container.page-exercise-plan .theme-thu { --fill:#d0ecef; --stroke:#9cc4c9; }
.page-container.page-exercise-plan .theme-fri { --fill:#f8d9cc; --stroke:#d4b09f; }
.page-container.page-exercise-plan .theme-sat { --fill:#e4e6ea; --stroke:#b5b7bb; }
.page-container.page-exercise-plan .theme-sun { --fill:#d4eadc; --stroke:#a3c4b0; }

/* Page 12 specific styles */
.lifestyle-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.lifestyle-section h3 {
    margin: 0 0 6px 0;
    font-size: 14px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.lifestyle-section-icon {
    width: 22px;
    height: 22px;
    object-fit: contain;
    flex-shrink: 0;
    display: block;
    color: #4b5563;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.lifestyle-section ul {
    margin: 0;
    padding-left: 16px;
}

.lifestyle-section li {
    font-size: 11px;
    line-height: 1.45;
    color: #333;
    margin-bottom: 4px;
}

/* Page 13 specific styles */
.supplement-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 14px;
}

.supplement-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 12px;
    display: flex;
    flex-direction: column;
}

.supplement-card h3 {
    margin: 0 0 8px 0;
    font-size: 13px;
    font-weight: 700;
    color: #1a1a1a;
}

.benefits-box {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 10px;
    flex: 1;
}

.benefits-box h4 {
    margin: 0 0 4px 0;
    font-size: 11px;
    font-weight: 700;
    color: #1a1a1a;
}

.benefits-box p {
    margin: 0;
    font-size: 10px;
    line-height: 1.4;
    color: #333;
}

/* Page 17 specific styles */
.warning-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.warning-container h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #1a1a1a;
}

.warning-box {
    background: #ffffff;
    border: 1px solid #fca5a5;
    border-radius: 6px;
    padding: 12px;
    color: #b91c1c;
    font-size: 11px;
    line-height: 1.5;
    font-weight: 500;
}

.company-info h2 {
    margin: 0 0 10px 0;
    font-size: 16px;
    font-weight: 800;
    color: #1a1a1a;
}

.company-details-grid {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    line-height: 1.5;
    color: #1a1a1a;
    font-weight: 600;
}

.company-details-left, .company-details-right {
    display: flex;
    flex-direction: column;
    gap: 2px;
}


/* =========================================================
   PRINT
========================================================= */

@page {
    size: A4 portrait;
    margin: 0;
}

@media print {

    :root {
        --page-margin: 10mm;
    }

    html, body {
        background: #ffffff !important;
        padding: 0 !important;
        margin: 0 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .page-container {
        margin: 0 !important;
        width: 210mm !important;
        min-height: 297mm !important;
        max-height: 297mm !important;
        height: 297mm !important;
        box-shadow: none !important;
        page-break-after: always;
        break-after: page;
        page-break-inside: avoid;
        overflow: hidden !important;
        display: flex !important;
        flex-direction: column !important;
        background: #e8ebfc !important;
    }

    .page-container:last-child {
        page-break-after: auto;
        break-after: auto;
    }

    .header {
        padding: var(--page-margin) var(--page-margin) 4mm var(--page-margin) !important;
        flex: 0 0 auto !important;
        background: linear-gradient(180deg, #f0eefc 0%, #e8ebfc 100%) !important;
    }
    .page-content {
        padding: 3mm var(--page-margin) 24px var(--page-margin) !important;
        display: flex !important;
        flex-direction: column !important;
        flex: 1 1 auto !important;
        background: #e8ebfc !important;
        min-height: 0 !important;
        justify-content: flex-start !important;
        gap: 8px !important;
        box-sizing: border-box !important;
        overflow: hidden !important;
    }
    .page-spacer {
        display: none !important;
        flex: 0 0 0 !important;
        height: 0 !important;
    }
    .footer-slot {
        display: block !important;
        visibility: hidden !important;
        flex: 0 0 var(--footer-height) !important;
        height: var(--footer-height) !important;
        max-height: var(--footer-height) !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
    }
    .page-content > :last-child:not(.page-spacer):not(.calculation-card):not(.biomarker-section-card):not(.warning-container):not(.organ-unused-note):not(.organ-card),
    .page-content > :has(+ .page-spacer):not(.calculation-card):not(.biomarker-section-card):not(.warning-container):not(.organ-unused-note):not(.organ-card) {
        flex: 1 1 auto !important;
        min-height: 0 !important;
    }
    .page-content > .calculation-card,
    .page-content > .biomarker-section-card,
    .page-content > .warning-container,
    .page-content > .organ-card.organ-unused-note,
    .page-content > .organ-card {
        flex: 0 0 auto !important;
        flex-grow: 0 !important;
        flex-shrink: 0 !important;
        height: auto !important;
        min-height: 0 !important;
    }
    .organ-card.organ-unused-note {
        display: block !important;
        width: 100% !important;
        background: #f8f8fa !important;
        border: 1px solid #e5e7eb !important;
        min-height: 36px !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .page-container.page-organ-health {
        padding-bottom: 0 !important;
        box-sizing: border-box !important;
    }
    .page-container.page-organ-health > .footer-slot {
        display: block !important;
        visibility: hidden !important;
        pointer-events: none !important;
        flex: 0 0 calc(var(--footer-height) + 4px) !important;
        width: 100% !important;
        height: calc(var(--footer-height) + 4px) !important;
        max-height: calc(var(--footer-height) + 4px) !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
    }
    .page-container.page-organ-health > .page-content {
        overflow: visible !important;
        padding-bottom: 0 !important;
    }
    .page-container.page-organ-health > .footer {
        position: absolute !important;
        left: 0 !important;
        bottom: 0 !important;
        width: 100% !important;
        height: var(--footer-height) !important;
    }
    .page-container.page-calc-safe {
        padding-bottom: 0 !important;
        box-sizing: border-box !important;
    }
    .page-container.page-calc-safe > .footer-slot {
        display: block !important;
        visibility: hidden !important;
        pointer-events: none !important;
        flex: 0 0 calc(var(--footer-height) + 4px) !important;
        width: 100% !important;
        height: calc(var(--footer-height) + 4px) !important;
        max-height: calc(var(--footer-height) + 4px) !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
    }
    .page-container.page-calc-safe > .page-content {
        flex: 1 1 0% !important;
        min-height: 0 !important;
        max-height: none !important;
        height: auto !important;
        box-sizing: border-box !important;
        overflow: visible !important;
        padding-bottom: 0 !important;
        justify-content: flex-start !important;
        align-content: flex-start !important;
    }
    .page-container.page-calc-safe > .page-content > :last-child {
        margin-bottom: 0 !important;
    }
    .page-container.page-calc-safe > .page-content > .calculation-card {
        flex: 0 0 auto !important;
        flex-grow: 0 !important;
        flex-shrink: 0 !important;
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        margin-bottom: 0 !important;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }
    .page-container.page-calc-safe > .footer {
        position: absolute !important;
        left: 0 !important;
        bottom: 0 !important;
        width: 100% !important;
        height: var(--footer-height) !important;
    }

    /* Weekly Exercise Plan — print / PDF (scoped) */
    .page-container.page-exercise-plan > .page-content {
        gap: 8px !important;
        padding-bottom: 0 !important;
        overflow: hidden !important;
    }
    .page-container.page-exercise-plan > .footer-slot {
        flex: 0 0 calc(var(--footer-height) + 4px) !important;
        height: calc(var(--footer-height) + 4px) !important;
        max-height: calc(var(--footer-height) + 4px) !important;
    }
    .page-container.page-exercise-plan .wep-list {
        gap: 8px !important;
        flex: 1 1 auto !important;
        min-height: 0 !important;
    }
    .page-container.page-exercise-plan .wep-row {
        flex: 0 0 100px !important;
        height: 100px !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .page-container.page-exercise-plan .wep-frame {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .page-container.page-exercise-plan .wep-icon img {
        width: 52px !important;
        height: 52px !important;
    }
    .page-container.page-exercise-plan .wep-day-name,
    .page-container.page-exercise-plan .wep-act-name,
    .page-container.page-exercise-plan .wep-rec-title {
        font-size: 12px !important;
    }
    .page-container.page-exercise-plan .wep-act-name,
    .page-container.page-exercise-plan .wep-rec-title {
        padding: 4px 0 2px !important;
    }
    .page-container.page-exercise-plan .wep-sets,
    .page-container.page-exercise-plan .wep-dur,
    .page-container.page-exercise-plan .wep-rec-text {
        font-size: 11.5px !important;
    }
    .page-container.page-exercise-plan .wep-rec-text {
        padding-top: 12px !important;
    }

    .biomarker-section-card,
    .biomarker-item,
    .value-box,
    .status-pill {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .biomarker-item {
        min-height: 68px !important;
    }

    .value-box {
        width: 68px !important;
        min-width: 68px !important;
        font-size: 15px !important;
    }

    .section-title-container {
        margin-left: calc(-1 * var(--page-margin)) !important;
        padding-left: var(--page-margin) !important;
    }
    .title-icon {
        left: var(--page-margin) !important;
    }
    .footer {
        position: absolute !important;
        left: 0 !important;
        bottom: 0 !important;
        width: 100% !important;
        height: var(--footer-height) !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
        background: #ffffff !important;
        box-shadow: none !important;
    }
    table.hero-section,
    table.info-grid,
    table.compare-section,
    table.insights-grid,
    .cards-grid,
    .process-card {
        flex: 1 1 auto !important;
        margin-bottom: 0 !important;
    }

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media screen and (max-width: 1050px) {

    .page-container {

        width: 95vw;

    }

    :root {
        --page-margin: 40px;
    }

    .cards-grid {

        grid-template-columns: 1fr;

    }

    .insights-grid {

        grid-template-columns: repeat(2, 1fr);

    }

}


/* =========================================================
   ICON COLORS
========================================================= */

.icon-red {
    stroke: #d93838;
}

.icon-green {
    stroke: #22c55e;
}

</style>
</head>


<body>


<!-- =======================================================
     PAGE 1
======================================================= -->

<div class="page-container">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>


<main class="page-content">


<!-- PATIENT INFO -->
<table class="patient-info-bar" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td>Patient Id: <span>079067</span></td>
    <td>Age: <span>41.0 Years</span></td>
    <td>Gender: <span>Male</span></td>
    <td>Test Date: <span>07/10/2025</span></td>
</tr></table>


<!-- HERO -->
<table class="hero-section" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="illust">
        <img class="hero-pace-clock" src="{{ asset('asset/image/v3_page_wise/page_01_front/front_page_pace_clock_icon.png') }}" alt="Pace clock" width="200" height="109">
    </td>
    <td class="ages">
        <table class="age-comparison" width="100%" cellpadding="0" cellspacing="0"><tr>
            <td>
                <div class="age-label">Your Age</div>
                <div class="age-value">41.0</div>
            </td>
            <td width="56">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M7 17l-5-5 5-5"/>
                    <path d="M17 7l5 5-5 5"/>
                    <path d="M2 12h20"/>
                </svg>
            </td>
            <td>
                <div class="age-label">Your Blood Age</div>
                <div class="age-value green">40.0</div>
            </td>
        </tr></table>
        <div class="hero-footer-text">Your biological age is one year younger than your chronological age.</div>
    </td>
</tr></table>


<!-- BLOOD AGE INFORMATION -->
<table class="info-grid" width="100%" cellpadding="0" cellspacing="0"><tr>
<td>
    <div class="page1-card-title">
        <img class="page1-title-icon" src="{{ asset('asset/image/v3_page_wise/page_01_front/what_is_blood_age_icon.png') }}" alt="" width="22" height="22">
        <h3 class="card-title">WHAT IS BLOOD AGE?</h3>
    </div>
    <p class="card-text">Blood Age provides a comprehensive snapshot of your biological aging. Using our patented AI "aging clock", we analyse specific cellular markers in your blood to determine your biological age, rather than just counting the years on the calendar based on your actual health status.</p>
</td>
<td>
    <div class="page1-card-title">
        <img class="page1-title-icon" src="{{ asset('asset/image/v3_page_wise/page_01_front/smiley_icon.png') }}" alt="" width="22" height="22">
        <h3 class="card-title">YOU ARE A SLOW AGER!</h3>
    </div>
    <p class="card-text" style="margin-bottom:4px;"><strong>Good news!</strong>
        <img class="page1-inline-icon" src="{{ asset('asset/image/v3_page_wise/page_01_front/thumbs_up_icon.png') }}" alt="" width="16" height="16">
        <strong>Your Biological Aging trajectory is favorable.</strong>
    </p>
    <p class="card-text">Your Blood Age is lower than your chronological age by one year, indicating strong health and a biological condition associated with slower aging.</p>
</td>
</tr></table>


<!-- COMPARE -->
<table class="compare-section" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="copy">
        <div class="compare-content">
            <h2>How do you compare?</h2>
            <p>We have compared your blood panel with samples in our database. Only 33.8% of your peers age slower than you. Great job!</p>
        </div>
    </td>
    <td class="gauge">
        <div class="gauge-container">
            <svg width="200" height="110" viewBox="0 0 220 120">
                <path d="M 20 110 A 90 90 0 0 1 37.2 57.1 L 58.1 74.7 A 60 60 0 0 0 50 110 Z" fill="#6bbf59" stroke="#ffffff" stroke-width="2"/>
                <path d="M 37.2 57.1 A 90 90 0 0 1 82.2 24.7 L 91.5 51.5 A 60 60 0 0 0 58.1 74.7 Z" fill="#a3d977" stroke="#ffffff" stroke-width="2"/>
                <path d="M 82.2 24.7 A 90 90 0 0 1 137.8 24.7 L 121.5 51.5 A 60 60 0 0 0 91.5 51.5 Z" fill="#fde047" stroke="#ffffff" stroke-width="2"/>
                <path d="M 137.8 24.7 A 90 90 0 0 1 182.8 57.1 L 161.9 74.7 A 60 60 0 0 0 121.5 51.5 Z" fill="#f59e0b" stroke="#ffffff" stroke-width="2"/>
                <path d="M 182.8 57.1 A 90 90 0 0 1 200 110 L 170 110 A 60 60 0 0 0 161.9 74.7 Z" fill="#dc2626" stroke="#ffffff" stroke-width="2"/>
                <line x1="110" y1="110" x2="75" y2="65" stroke="#1a1a1a" stroke-width="4" stroke-linecap="round"/>
                <circle cx="110" cy="110" r="8" fill="#1a1a1a"/>
            </svg>
        </div>
        <div class="gauge-label">Top 33.8%</div>
    </td>
</tr></table>


<!-- QUICK INSIGHTS -->
<h2 class="insights-title">Quick Insights</h2>
<table class="insights-grid" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td>
        <div class="insight-heading">
            <img class="insight-icon" src="{{ asset('asset/image/v3_page_wise/page_01_front/quick_insights_organs_card_icon.png') }}" alt="" width="40" height="32">
            <h3>ORGANS</h3>
        </div>
        <p><strong>8</strong> Organ systems<br>evaluated</p>
    </td>
    <td>
        <div class="insight-heading">
            <img class="insight-icon" src="{{ asset('asset/image/v3_page_wise/page_01_front/quick_insights_biomarkers_card_icon.png') }}" alt="" width="40" height="32">
            <h3>BIOMARKERS</h3>
        </div>
        <p><strong>32</strong> Blood Biomarkers<br>analysed</p>
    </td>
    <td>
        <div class="insight-heading">
            <img class="insight-icon" src="{{ asset('asset/image/v3_page_wise/page_01_front/quick_insights_aging_card_icon.png') }}" alt="" width="40" height="32">
            <h3>AGING</h3>
        </div>
        <p><strong>3</strong> Aging Biomarkers<br>found</p>
    </td>
    <td>
        <div class="insight-heading">
            <img class="insight-icon" src="{{ asset('asset/image/v3_page_wise/page_01_front/quick_insights_antiaging_card_icon.png') }}" alt="" width="40" height="32">
            <h3>ANTI-AGING</h3>
        </div>
        <p><strong>28</strong> Biomarkers are<br>Anti-Aging</p>
    </td>
</tr></table>

<div class="page-spacer"></div>
</main>


<!-- FOOTER -->

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">

    <div class="footer-bg-bar"></div>

    <div class="footer-shapes">

        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>

    </div>


    <div class="footer-content-left">

        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>


        <p class="footer-copyright">
            © Mulkmed Healthcare, 2026. All Rights Reserved.
        </p>

    </div>


    <div class="page-number">
        Page 1 of 18
    </div>

</footer>

</div>


<!-- =======================================================
     PAGE 2
======================================================= -->

<div class="page-container">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>


<main class="page-content">


<!-- =====================================================
     ABOUT BLOOD AGE REPORT
===================================================== -->

<div class="section-title-container"
     style="margin-top:8px;">

    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_02_what_is_blood_age/about_blood_age_report_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>


    <div class="title-bar">

        <h2>
            About Blood Age Report
        </h2>

    </div>

</div>


<!-- INTRO 1 -->

<p class="intro-text">

Blood Age is a unique statistic that summarises your health. It offers a precise measure of aging, revealing blood-based changes and wider physiological trends to guide your personalised health management.

</p>


<!-- INTRO 2 -->

<p class="intro-text">

Blood Age analyses 45 key blood biomarkers to estimate your biological age relative to your chronological age and evaluate how certain organ systems are functioning using 9 additional blood biomarkers. Using simple blood test data, it provides an AI data-driven view of your internal health – guiding smarter decisions for healthier, longer living.

</p>


<!-- =====================================================
     THREE INFORMATION CARDS
===================================================== -->

<div class="cards-grid">


<!-- CARD 1 -->

<div class="info-card">

    <div class="card-header">

        <img src="{{ asset('asset/image/v3_page_wise/page_02_what_is_blood_age/precision_age_estimation_card_icon.png') }}" alt="" width="22" height="22">


        <h3>
            Peer Comparison
        </h3>

    </div>


    <p>

Blood Age report compares how a person is doing relative to their global peers of the same age and gender, using our extensive dataset trained on our Model to deliver highly accurate health insights.

    </p>

</div>


<!-- CARD 2 -->

<div class="info-card">

    <div class="card-header">

        <img src="{{ asset('asset/image/v3_page_wise/page_02_what_is_blood_age/organ_health_insights_card_icon.png') }}" alt="" width="22" height="22">


        <h3>
            Organ Health Insights
        </h3>

    </div>


    <p>

Evaluates eight organ health systems — Immune, Cardiovascular, Inflammation, Mineral, Kidney, Glucidic, Bone, and Liver Health — to reveal body's functional status and enable early detection of emerging physiological risks.

    </p>

</div>


<!-- CARD 3 -->

<div class="info-card">

    <div class="card-header">

        <img src="{{ asset('asset/image/v3_page_wise/page_02_what_is_blood_age/personalized_guidance_card_icon.png') }}" alt="" width="22" height="22">


        <h3>
            Personalized Guidance
        </h3>

    </div>


    <p>

Delivers tailored, science-backed guidance on diet, exercise, lifestyle, and supplements based on your age, gender, and health profile, helping you make informed decisions that support healthier aging and long-term well-being.

    </p>

</div>

</div>


<!-- =====================================================
     HOW IT WORKS
===================================================== -->

<div class="section-title-container"
     style="margin-top:10px;">

    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_02_what_is_blood_age/how_it_works_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>


    <div class="title-bar"
         style="width:520px;">

        <h2>
            How it works?
        </h2>

    </div>

</div>


<!-- =====================================================
     PROCESS 01
===================================================== -->

<div class="process-card">

    <div class="process-number-block bg-purple">

        <div class="number">
            01
        </div>

        <div class="label">
            File Intake
        </div>

    </div>


    <div class="process-content">

        <h3>
            Digital Blood Test Data Intake
        </h3>

        <p>

Our platform accepts digital blood test data in PDF or flat-file format and securely extracts the required biomarkers for analysis. The reports may be in any format, in any language, and from any lab in the world, and can be shared through APIs or our SaaS platforms.

        </p>

    </div>

</div>


<!-- =====================================================
     PROCESS 02
===================================================== -->

<div class="process-card">

    <div class="process-number-block bg-yellow">

        <div class="number">
            02
        </div>

        <div class="label">
            Analysis
        </div>

    </div>


    <div class="process-content">

        <h3>
            Advanced Biomarker AI Analysis
        </h3>

        <p>

A patented AI model developed by training on a global curated dataset of hundreds of thousands of individuals across diverse ages and ethnic backgrounds, evaluates biomarker patterns to interpret aging dynamics and organ-level trends.

        </p>

    </div>

</div>


<!-- =====================================================
     PROCESS 03
===================================================== -->

<div class="process-card">

    <div class="process-number-block bg-turquoise">

        <div class="number">
            03
        </div>

        <div class="label">
            Result
        </div>

    </div>


    <div class="process-content">

        <h3>
            Assessment & Insights
        </h3>

        <p>

Your blood test data is translated into an aging assessment that includes biological age prediction and organ-health insights. This scientifically grounded interpretation enables meaningful, personalized guidance to support proactive and healthier long-term aging.

        </p>

    </div>

</div>


<div class="page-spacer"></div>
</main>


<!-- =====================================================
     FOOTER PAGE 2
===================================================== -->

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">

    <div class="footer-bg-bar"></div>

    <div class="footer-shapes">

        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>

    </div>


    <div class="footer-content-left">

        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>


        <p class="footer-copyright">
            © Mulkmed Healthcare, 2026. All Rights Reserved.
        </p>

    </div>


    <div class="page-number">
        Page 2 of 18
    </div>

</footer>

</div>


<!-- =======================================================
     PAGE 3
======================================================= -->

<div class="page-container">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>


<main class="page-content">

<div class="section-title-container">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_03_impact_of_key_biomarkers/impact_of_key_biomarkers_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:520px;">
        <h2>Impact of Key Biomarkers</h2>
    </div>
</div>

<div class="intro-text-block">
    <p>The diagram highlights the 14 biomarkers with the strongest influence, expressed as age equivalents.</p>
    <ul>
        <li>Red markers increase Blood Age; green markers reduce it.</li>
        <li>Your final Blood Age reflects the combined effect of all biomarkers.</li>
    </ul>
</div>

<div class="biomarker-headers">
    <div class="header-left-group">
        <div class="header-pill green">Anti-Aging Biomarkers</div>
        <div class="header-btn green-btn">+</div>
    </div>
    <div class="header-right-group">
        <div class="header-btn red-btn">−</div>
        <div class="header-pill red">Aging Biomarkers</div>
    </div>
</div>

<div class="biomarker-section">
    <div class="biomarker-column left">
        <div class="biomarker-row">
            <div class="biomarker-name">Phosphorous</div>
            <div class="biomarker-percentage-pill green">1.2%</div>
        </div>
        <div class="biomarker-row">
            <div class="biomarker-name">Mean Corpuscular Volume</div>
            <div class="biomarker-percentage-pill green">1.4%</div>
        </div>
        <div class="biomarker-row">
            <div class="biomarker-name">Aspartate Aminotransferase</div>
            <div class="biomarker-percentage-pill green">1.4%</div>
        </div>
        <div class="biomarker-row">
            <div class="biomarker-name">Mean Corpuscular Hemoglobin</div>
            <div class="biomarker-percentage-pill green">1.7%</div>
        </div>
        <div class="biomarker-row">
            <div class="biomarker-name">Monocytes, %</div>
            <div class="biomarker-percentage-pill green">1.9%</div>
        </div>
        <div class="biomarker-row">
            <div class="biomarker-name">Basophils,%</div>
            <div class="biomarker-percentage-pill green">2.2%</div>
        </div>
        <div class="biomarker-row">
            <div class="biomarker-name">Alanine Transaminase</div>
            <div class="biomarker-percentage-pill green">2.6%</div>
        </div>
        <div class="biomarker-row">
            <div class="biomarker-name">Hemoglobin A1c</div>
            <div class="biomarker-percentage-pill green">3.1%</div>
        </div>
        <div class="biomarker-row">
            <div class="biomarker-name">Platelets</div>
            <div class="biomarker-percentage-pill green">3.3%</div>
        </div>
        <div class="biomarker-row">
            <div class="biomarker-name">Lymphocytes, %</div>
            <div class="biomarker-percentage-pill green">3.6%</div>
        </div>
        <div class="biomarker-row">
            <div class="biomarker-name">Red Blood Cells</div>
            <div class="biomarker-percentage-pill green">3.7%</div>
        </div>
    </div>

    <div class="center-indicator">
        <img class="biomarker-runner" src="{{ asset('asset/image/v3_page_wise/page_03_impact_of_key_biomarkers/vintage_world_running_green.png') }}" alt="" width="84" height="62">
        <div class="indicator-pill">1.0</div>
        <img class="biomarker-runner" src="{{ asset('asset/image/v3_page_wise/page_03_impact_of_key_biomarkers/vintage_world_running_red.png') }}" alt="" width="84" height="62">
    </div>

    <div class="biomarker-column right">
        <div class="biomarker-row">
            <div class="biomarker-name">Blood Urea Nitrogen</div>
            <div class="biomarker-percentage-pill red">60.9%</div>
        </div>
        <div class="biomarker-row">
            <div class="biomarker-name">White Blood Cells</div>
            <div class="biomarker-percentage-pill red">35.0%</div>
        </div>
        <div class="biomarker-row">
            <div class="biomarker-name">Glucose Fasting</div>
            <div class="biomarker-percentage-pill red">4.2%</div>
        </div>
    </div>
</div>

</main>

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">
    <div class="footer-bg-bar"></div>
    <div class="footer-shapes">
        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>
    </div>
    <div class="footer-content-left">
        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>
        <p class="footer-copyright">© Mulkmed Healthcare, 2026. All Rights Reserved.</p>
    </div>
    <div class="page-number">Page 3 of 18</div>
</footer>

</div>


<!-- =======================================================
     PAGE 4
======================================================= -->

<div class="page-container page-calc-safe">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>

<main class="page-content">

<div class="section-title-container">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_04_blood_age_calculation/blood_age_calculation_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:520px;">
        <h2>Blood Age Calculation</h2>
    </div>
</div>

<div class="calculation-card">
    <h3 class="card-title">Cholesterol Status</h3>
    <div class="aging-scale">
        <div class="aging-scale-header">
            <span class="anti-aging">Anti-aging</span>
            <span class="impact-years">Impact (Years)</span>
            <span class="aging">Aging</span>
        </div>
        <div class="aging-scale-bar">
            <div class="anti-aging-bar"></div>
            <div class="aging-bar"></div>
        </div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">HDL Cholesterol</div>
        <div class="impact-value-track align-neutral"><span class="value-pill neutral">0</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">LDL Cholesterol</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.04</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Triglycerides</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.31</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Total Cholesterol</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.03</span></div>
    </div>
</div>

<div class="calculation-card">
    <h3 class="card-title">Kidney Status</h3>
    <div class="aging-scale">
        <div class="aging-scale-header">
            <span class="anti-aging">Anti-aging</span>
            <span class="impact-years">Impact (Years)</span>
            <span class="aging">Aging</span>
        </div>
        <div class="aging-scale-bar">
            <div class="anti-aging-bar"></div>
            <div class="aging-bar"></div>
        </div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Blood Urea Nitrogen</div>
        <div class="impact-value-track align-aging"><span class="value-pill red">3.16</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Creatinine</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.26</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Uric Acid</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.24</span></div>
    </div>
</div>

<div class="calculation-card">
    <h3 class="card-title">Liver Status</h3>
    <div class="aging-scale">
        <div class="aging-scale-header">
            <span class="anti-aging">Anti-aging</span>
            <span class="impact-years">Impact (Years)</span>
            <span class="aging">Aging</span>
        </div>
        <div class="aging-scale-bar">
            <div class="anti-aging-bar"></div>
            <div class="aging-bar"></div>
        </div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Alkaline Phosphatase <span class="not-available-pill">Not Available</span></div>
        <div class="impact-value-track align-empty"></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Alanine Transaminase</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.16</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Aspartate Aminotransferase</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.09</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Direct Bilirubin <span class="not-available-pill">Not Available</span></div>
        <div class="impact-value-track align-empty"></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Total Bilirubin <span class="not-available-pill">Not Available</span></div>
        <div class="impact-value-track align-empty"></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Gamma-GT <span class="not-available-pill">Not Available</span></div>
        <div class="impact-value-track align-empty"></div>
    </div>
</div>

</main>

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">
    <div class="footer-bg-bar"></div>
    <div class="footer-shapes">
        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>
    </div>
    <div class="footer-content-left">
        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>
        <p class="footer-copyright">© Mulkmed Healthcare, 2026. All Rights Reserved.</p>
    </div>
    <div class="page-number">Page 4 of 18</div>
</footer>

</div>


<!-- =======================================================
     PAGE 5
======================================================= -->

<div class="page-container page-calc-safe">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>

<main class="page-content">

<div class="section-title-container">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_04_blood_age_calculation/blood_age_calculation_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:520px;">
        <h2>Blood Age Calculation</h2>
    </div>
</div>

<div class="calculation-card">
    <h3 class="card-title">Glucose Status</h3>
    <div class="aging-scale">
        <div class="aging-scale-header">
            <span class="anti-aging">Anti-aging</span>
            <span class="impact-years">Impact (Years)</span>
            <span class="aging">Aging</span>
        </div>
        <div class="aging-scale-bar">
            <div class="anti-aging-bar"></div>
            <div class="aging-bar"></div>
        </div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Glucose Fasting</div>
        <div class="impact-value-track align-aging"><span class="value-pill red">0.22</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Hemoglobin A1c</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.19</span></div>
    </div>
</div>

<div class="calculation-card">
    <h3 class="card-title">Red Blood Cells Status</h3>
    <div class="aging-scale">
        <div class="aging-scale-header">
            <span class="anti-aging">Anti-aging</span>
            <span class="impact-years">Impact (Years)</span>
            <span class="aging">Aging</span>
        </div>
        <div class="aging-scale-bar">
            <div class="anti-aging-bar"></div>
            <div class="aging-bar"></div>
        </div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Hematocrit</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.29</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Hemoglobin</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.29</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Mean Corpuscular Hemoglobin</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.11</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">MCHC</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.32</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Mean Corpuscular Volume</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.08</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Red Blood Cells</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.23</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Red Cell Distribution Width <span class="not-available-pill">Not Available</span></div>
        <div class="impact-value-track align-empty"></div>
    </div>
</div>

</main>

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">
    <div class="footer-bg-bar"></div>
    <div class="footer-shapes">
        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>
    </div>
    <div class="footer-content-left">
        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>
        <p class="footer-copyright">© Mulkmed Healthcare, 2026. All Rights Reserved.</p>
    </div>
    <div class="page-number">Page 5 of 18</div>
</footer>

</div>


<!-- =======================================================
     PAGE 6
======================================================= -->

<div class="page-container page-calc-safe">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>

<main class="page-content">

<div class="section-title-container">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_04_blood_age_calculation/blood_age_calculation_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:520px;">
        <h2>Blood Age Calculation</h2>
    </div>
</div>

<div class="calculation-card">
    <h3 class="card-title">White Blood Cells Status</h3>
    <div class="aging-scale">
        <div class="aging-scale-header">
            <span class="anti-aging">Anti-aging</span>
            <span class="impact-years">Impact (Years)</span>
            <span class="aging">Aging</span>
        </div>
        <div class="aging-scale-bar">
            <div class="anti-aging-bar"></div>
            <div class="aging-bar"></div>
        </div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Basophils, %</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.13</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Eosinophils, %</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.27</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Lymphocytes, %</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.23</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Monocytes, %</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.12</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Neutrophils,%</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.31</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">White Blood Cell</div>
        <div class="impact-value-track align-aging"><span class="value-pill red">1.81</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Atypical Lymphocyte <span class="not-available-pill">Not Available</span></div>
        <div class="impact-value-track align-empty"></div>
    </div>
</div>

<div class="calculation-card">
    <h3 class="card-title">Clotting Status</h3>
    <div class="aging-scale">
        <div class="aging-scale-header">
            <span class="anti-aging">Anti-aging</span>
            <span class="impact-years">Impact (Years)</span>
            <span class="aging">Aging</span>
        </div>
        <div class="aging-scale-bar">
            <div class="anti-aging-bar"></div>
            <div class="aging-bar"></div>
        </div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Platelets</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.2</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Mean Platelet Volume</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.34</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Erythrocyte Sedimentation Rate <span class="not-available-pill">Not Available</span></div>
        <div class="impact-value-track align-empty"></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Platelet Distribution Width <span class="not-available-pill">Not Available</span></div>
        <div class="impact-value-track align-empty"></div>
    </div>
</div>

<div class="calculation-card" style="margin-bottom:0;">
    <h3 class="card-title">Iron Status</h3>
    <div class="aging-scale">
        <div class="aging-scale-header">
            <span class="anti-aging">Anti-aging</span>
            <span class="impact-years">Impact (Years)</span>
            <span class="aging">Aging</span>
        </div>
        <div class="aging-scale-bar">
            <div class="anti-aging-bar"></div>
            <div class="aging-bar"></div>
        </div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Ferritin</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.35</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Iron <span class="not-available-pill">Not Available</span></div>
        <div class="impact-value-track align-empty"></div>
    </div>
</div>

</main>

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">
    <div class="footer-bg-bar"></div>
    <div class="footer-shapes">
        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>
    </div>
    <div class="footer-content-left">
        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>
        <p class="footer-copyright">© Mulkmed Healthcare, 2026. All Rights Reserved.</p>
    </div>
    <div class="page-number">Page 6 of 18</div>
</footer>

</div>


<!-- =======================================================
     PAGE 7 — Minerals / Proteins / Other (whole cards only)
======================================================= -->

<div class="page-container page-calc-safe">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>

<main class="page-content">

<div class="section-title-container">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_04_blood_age_calculation/blood_age_calculation_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:520px;">
        <h2>Blood Age Calculation</h2>
    </div>
</div>

<div class="calculation-card">
    <h3 class="card-title">Minerals Status</h3>
    <div class="aging-scale">
        <div class="aging-scale-header">
            <span class="anti-aging">Anti-aging</span>
            <span class="impact-years">Impact (Years)</span>
            <span class="aging">Aging</span>
        </div>
        <div class="aging-scale-bar">
            <div class="anti-aging-bar"></div>
            <div class="aging-bar"></div>
        </div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Calcium</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.26</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Chloride</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.3</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Potassium</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.32</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Sodium</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.33</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Phosphorous</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.08</span></div>
    </div>
</div>

<div class="calculation-card">
    <h3 class="card-title">Proteins Status</h3>
    <div class="aging-scale">
        <div class="aging-scale-header">
            <span class="anti-aging">Anti-aging</span>
            <span class="impact-years">Impact (Years)</span>
            <span class="aging">Aging</span>
        </div>
        <div class="aging-scale-bar">
            <div class="anti-aging-bar"></div>
            <div class="aging-bar"></div>
        </div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Albumin <span class="not-available-pill">Not Available</span></div>
        <div class="impact-value-track align-empty"></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Total Globulin <span class="not-available-pill">Not Available</span></div>
        <div class="impact-value-track align-empty"></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Total Protein <span class="not-available-pill">Not Available</span></div>
        <div class="impact-value-track align-empty"></div>
    </div>
</div>

<div class="calculation-card" style="margin-bottom:0;">
    <h3 class="card-title">Other Status</h3>
    <div class="aging-scale">
        <div class="aging-scale-header">
            <span class="anti-aging">Anti-aging</span>
            <span class="impact-years">Impact (Years)</span>
            <span class="aging">Aging</span>
        </div>
        <div class="aging-scale-bar">
            <div class="anti-aging-bar"></div>
            <div class="aging-bar"></div>
        </div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Alpha-Fetoprotein</div>
        <div class="impact-value-track align-anti"><span class="value-pill green">-0.3</span></div>
    </div>
    <div class="impact-data-row">
        <div class="impact-row-label">Alpha-Amylase <span class="not-available-pill">Not Available</span></div>
        <div class="impact-value-track align-empty"></div>
    </div>
</div>

</main>

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">
    <div class="footer-bg-bar"></div>
    <div class="footer-shapes">
        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>
    </div>
    <div class="footer-content-left">
        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>
        <p class="footer-copyright">© Mulkmed Healthcare, 2026. All Rights Reserved.</p>
    </div>
    <div class="page-number">Page 7 of 18</div>
</footer>

</div>


<!-- =======================================================
     PAGE 8
======================================================= -->

<div class="page-container">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>

<main class="page-content">

<div class="section-title-container">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_05_how_blood_age_calculated/blood_age_calculation_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:520px;">
        <h2>How Your Blood Age Was Calculated</h2>
    </div>
</div>

<div class="calc-card">
    <div class="calc-row">
        <span>Sum Impact Aging</span>
        <span class="calc-badge red">5.2 Years</span>
        <span style="margin-left:20px;">Sum Impact Anti-Aging</span>
        <span class="calc-badge green">-6.2 Years</span>
    </div>
    <div class="calc-row" style="font-size:12px;">
        Blood Age = Chrono Age + Sum Impact Aging + Sum Impact Anti-Aging
    </div>
    <div class="calc-row" style="margin-top:8px;">
        <span class="calc-badge pink">Blood Age</span>
        <span>=</span>
        <div class="calc-marker black"><span>41.0</span></div>
        <span>+</span>
        <span class="calc-badge red">5.2 Years</span>
        <span>+</span>
        <span class="calc-badge green">-6.2 Years</span>
        <span>=</span>
        <div class="calc-marker red"><span>40.0</span></div>
    </div>
</div>

<div class="section-title-container" style="margin-top:6px;">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_05_how_blood_age_calculated/blood_age_vs_actual_age_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:420px;">
        <h2>Blood Age &lt; Actual Age</h2>
    </div>
</div>

<div class="info-card-row">
    <div class="info-card-icon green">
        <img src="{{ asset('asset/image/v3_page_wise/page_05_how_blood_age_calculated/anti_aging_middle_card_icon.png') }}" alt="" width="56" height="56">
    </div>
    <div class="info-card-content">
        <div class="info-card-pill">Slower Aging (Lower Risk)</div>
        <p>When blood age is younger than actual age, it reflects slower aging and better health.</p>
    </div>
</div>

<div class="section-title-container" style="margin-top:6px;">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_05_how_blood_age_calculated/health_improvements_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:420px;">
        <h2>Health Improvements</h2>
    </div>
</div>

<div class="info-card-row">
    <div class="info-card-icon green">
        <img src="{{ asset('asset/image/v3_page_wise/page_05_how_blood_age_calculated/immune_health_card_icon.png') }}" alt="" width="56" height="56">
    </div>
    <div class="info-card-content">
        <h3>Immune Health — Scope for Further Strengthening</h3>
        <p>Your immune indicators show generally stable performance with signs of resilience, but there is still room to strengthen adaptive response and recovery efficiency. Continued focus on balanced nutrition, sleep, and stress management can help further enhance immune stability.</p>
    </div>
</div>

<div class="info-card-row">
    <div class="info-card-icon yellow">
        <img src="{{ asset('asset/image/v3_page_wise/page_05_how_blood_age_calculated/cardiovascular_health_card_icon.png') }}" alt="" width="56" height="56">
    </div>
    <div class="info-card-content">
        <h3>Cardiovascular Health — Moderate but Can Improve</h3>
        <p>Cardiovascular markers reflect acceptable function with positive tendencies; however, certain patterns indicate that circulatory efficiency and vascular support could benefit from additional optimization. Consistent physical activity and heart-healthy habits may enhance long-term cardiovascular stability.</p>
    </div>
</div>

<div class="info-card-row" style="margin-bottom:0;">
    <div class="info-card-icon red">
        <img src="{{ asset('asset/image/v3_page_wise/page_05_how_blood_age_calculated/inflammation_health_card_icon.png') }}" alt="" width="56" height="56">
    </div>
    <div class="info-card-content">
        <h3>Inflammation Health — Needs Additional Optimization</h3>
        <p>Inflammation levels appear reasonably controlled, yet subtle variations indicate that inflammatory balance is not fully optimal. Supporting your routine with anti-inflammatory nutrition, regular movement, and effective stress regulation may help bring these markers into an even healthier range.</p>
    </div>
</div>

</main>

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">
    <div class="footer-bg-bar"></div>
    <div class="footer-shapes">
        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>
    </div>
    <div class="footer-content-left">
        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>
        <p class="footer-copyright">© Mulkmed Healthcare, 2026. All Rights Reserved.</p>
    </div>
    <div class="page-number">Page 8 of 18</div>
</footer>

</div>


<!-- =======================================================
     PAGE 9
======================================================= -->

<div class="page-container page-organ-health">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>

<main class="page-content">

<div class="section-title-container">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_06_organ_health_scores/organ_health_scores_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:520px;">
        <h2>Organ Health Scores</h2>
    </div>
</div>

<div class="score-legend">
    <div class="score-legend-title">Score Legends</div>
    <div class="score-legend-items">
        <div class="legend-item"><div class="legend-color good"></div> Good(60-100)</div>
        <div class="legend-item"><div class="legend-color moderate"></div> Moderate(40-59)</div>
        <div class="legend-item"><div class="legend-color poor"></div> Poor(0-39)</div>
        <div class="legend-item"><div class="legend-triangle"></div> Baseline</div>
    </div>
</div>

<div class="organ-card">
    <div class="organ-header">
        <div class="organ-title-group">
            <img src="{{ asset('asset/image/v3_page_wise/page_06_organ_health_scores/immune_health_score_card_icon.png') }}" alt="" width="22" height="22">
            <span>Immune Health</span>
        </div>
        <div class="organ-score">63%</div>
    </div>
    <div class="organ-bar-container">
        <div class="organ-bar-fill" style="width: 63%; background: #79AC78;"></div>
        <div class="organ-baseline" style="left: 63%;"></div>
    </div>
    <div class="organ-info-grid">
        <div class="organ-info-box">
            <h4>Description:</h4>
            <p>Reflects the body's general ability to defend against routine infections and maintain balanced immune responses essential for everyday resilience and overall well-being.</p>
        </div>
        <div class="organ-info-box">
            <h4>Related Biomarkers:</h4>
            <p>Basophils, % *C-reactive protein (CRP), Eosinophils%, *ESR, ALT, *Total Protein, Lymphocytes%</p>
        </div>
    </div>
</div>

<div class="organ-card">
    <div class="organ-header">
        <div class="organ-title-group">
            <img src="{{ asset('asset/image/v3_page_wise/page_06_organ_health_scores/liver_health_score_card_icon.png') }}" alt="" width="22" height="22">
            <span>Liver Health</span>
        </div>
        <div class="organ-score">77%</div>
    </div>
    <div class="organ-bar-container">
        <div class="organ-bar-fill" style="width: 77%; background: #79AC78;"></div>
        <div class="organ-baseline" style="left: 77%;"></div>
    </div>
    <div class="organ-info-grid">
        <div class="organ-info-box">
            <h4>Description:</h4>
            <p>Indicates the liver's overall functional efficiency in processing nutrients, filtering routine waste, and supporting metabolic balance important for daily physiological stability.</p>
        </div>
        <div class="organ-info-box">
            <h4>Related Biomarkers:</h4>
            <p>Alanine aminotransferase (ALT), *Alkaline phosphatase (ALP), *Bilirubin Direct, Aspartate aminotransferase (AST)</p>
        </div>
    </div>
</div>

<div class="organ-card">
    <div class="organ-header">
        <div class="organ-title-group">
            <img src="{{ asset('asset/image/v3_page_wise/page_06_organ_health_scores/bone_health_score_card_icon.png') }}" alt="" width="22" height="22">
            <span>Bone Health</span>
        </div>
        <div class="organ-score">59%</div>
    </div>
    <div class="organ-bar-container">
        <div class="organ-bar-fill" style="width: 59%; background: #FCD34D;"></div>
        <div class="organ-baseline" style="left: 59%;"></div>
    </div>
    <div class="organ-info-grid">
        <div class="organ-info-box">
            <h4>Description:</h4>
            <p>Represents the general condition of bones in terms of strength and mineral support, helping maintain structural stability and everyday mobility across life stages.</p>
        </div>
        <div class="organ-info-box">
            <h4>Related Biomarkers:</h4>
            <p>*Alkaline phosphatase (ALP), Calcium, *Parathyroid hormone (PTH), Phosphorous</p>
        </div>
    </div>
</div>

<div class="organ-card">
    <div class="organ-header">
        <div class="organ-title-group">
            <img src="{{ asset('asset/image/v3_page_wise/page_06_organ_health_scores/cardiovascular_health_score_card_icon.png') }}" alt="" width="22" height="22">
            <span>Cardiovascular Health</span>
        </div>
        <div class="organ-score">47%</div>
    </div>
    <div class="organ-bar-container">
        <div class="organ-bar-fill" style="width: 47%; background: #FCD34D;"></div>
        <div class="organ-baseline" style="left: 47%;"></div>
    </div>
    <div class="organ-info-grid">
        <div class="organ-info-box">
            <h4>Description:</h4>
            <p>Describes the overall functioning of the heart and blood vessels in supporting healthy circulation, everyday stamina, and efficient delivery of oxygen and nutrients.</p>
        </div>
        <div class="organ-info-box">
            <h4>Related Biomarkers:</h4>
            <p>*Apolipoprotein B, *C-reactive Protein (CRP), HDL Cholesterol, Lactate Dehydrogenase, LDL Cholesterol, Total Cholesterol, Triglycerides</p>
        </div>
    </div>
</div>

<div class="organ-card organ-unused-note">*Unused / Unavailable Biomarkers</div>

</main>

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">
    <div class="footer-bg-bar"></div>
    <div class="footer-shapes">
        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>
    </div>
    <div class="footer-content-left">
        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>
        <p class="footer-copyright">© Mulkmed Healthcare, 2026. All Rights Reserved.</p>
    </div>
    <div class="page-number">Page 9 of 18</div>
</footer>

</div>


<!-- =======================================================
     PAGE 10
======================================================= -->

<div class="page-container page-organ-health">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>

<main class="page-content">

<div class="section-title-container">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_06_organ_health_scores/organ_health_scores_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:520px;">
        <h2>Organ Health Scores</h2>
    </div>
</div>

<div class="score-legend">
    <div class="score-legend-title">Score Legends</div>
    <div class="score-legend-items">
        <div class="legend-item"><div class="legend-color good"></div> Good(60-100)</div>
        <div class="legend-item"><div class="legend-color moderate"></div> Moderate(40-59)</div>
        <div class="legend-item"><div class="legend-color poor"></div> Poor(0-39)</div>
        <div class="legend-item"><div class="legend-triangle"></div> Baseline</div>
    </div>
</div>

<div class="organ-card">
    <div class="organ-header">
        <div class="organ-title-group">
            <img src="{{ asset('asset/image/v3_page_wise/page_06_organ_health_scores/glucidic_health_score_card_icon.png') }}" alt="" width="22" height="22">
            <span>Glucidic Health</span>
        </div>
        <div class="organ-score">45%</div>
    </div>
    <div class="organ-bar-container">
        <div class="organ-bar-fill" style="width: 45%; background: #FCD34D;"></div>
        <div class="organ-baseline" style="left: 45%;"></div>
    </div>
    <div class="organ-info-grid">
        <div class="organ-info-box">
            <h4>Description:</h4>
            <p>Indicates the body's general ability to manage blood sugar levels and support stable energy regulation through routine metabolic processes.</p>
        </div>
        <div class="organ-info-box">
            <h4>Related Biomarkers:</h4>
            <p>*C-peptide, Glucose Fasting, Hemoglobin A1C, *Insulin-like Growth Factor-1</p>
        </div>
    </div>
</div>

<div class="organ-card">
    <div class="organ-header">
        <div class="organ-title-group">
            <img src="{{ asset('asset/image/v3_page_wise/page_06_organ_health_scores/inflammation_health_score_card_icon.png') }}" alt="" width="22" height="22">
            <span>Inflammation Health</span>
        </div>
        <div class="organ-score">28%</div>
    </div>
    <div class="organ-bar-container">
        <div class="organ-bar-fill" style="width: 28%; background: #BD574E;"></div>
        <div class="organ-baseline" style="left: 28%;"></div>
    </div>
    <div class="organ-info-grid">
        <div class="organ-info-box">
            <h4>Description:</h4>
            <p>Reflects the balance of typical inflammatory responses in the body, supporting normal recovery, tissue maintenance, and overall systemic stability.</p>
        </div>
        <div class="organ-info-box">
            <h4>Related Biomarkers:</h4>
            <p>*C-reactive Protein (CRP), *Erythrocyte Sedimentation Rate (ESR), Ferritin, Neutrophils%, *Total Protein</p>
        </div>
    </div>
</div>

<div class="organ-card">
    <div class="organ-header">
        <div class="organ-title-group">
            <img src="{{ asset('asset/image/v3_page_wise/page_06_organ_health_scores/mineral_health_score_card_icon.png') }}" alt="" width="22" height="22">
            <span>Mineral Health</span>
        </div>
        <div class="organ-score">72%</div>
    </div>
    <div class="organ-bar-container">
        <div class="organ-bar-fill" style="width: 72%; background: #79AC78;"></div>
        <div class="organ-baseline" style="left: 72%;"></div>
    </div>
    <div class="organ-info-grid">
        <div class="organ-info-box">
            <h4>Description:</h4>
            <p>Represents the body's overall mineral balance essential for routine cellular functions, energy processes, nerve signaling, bone support, and general metabolic stability.</p>
        </div>
        <div class="organ-info-box">
            <h4>Related Biomarkers:</h4>
            <p>Basophils, % *C-reactive Protein (CRP), Eosinophils%, *ESR, ALT, *Total Protein, Lymphocytes%</p>
        </div>
    </div>
</div>

<div class="organ-card">
    <div class="organ-header">
        <div class="organ-title-group">
            <img src="{{ asset('asset/image/v3_page_wise/page_06_organ_health_scores/kidney_health_score_card_icon.png') }}" alt="" width="22" height="22">
            <span>Kidney Health</span>
        </div>
        <div class="organ-score">71%</div>
    </div>
    <div class="organ-bar-container">
        <div class="organ-bar-fill" style="width: 71%; background: #79AC78;"></div>
        <div class="organ-baseline" style="left: 71%;"></div>
    </div>
    <div class="organ-info-grid">
        <div class="organ-info-box">
            <h4>Description:</h4>
            <p>Indicates the kidneys' general efficiency in filtering routine waste, maintaining fluid and electrolyte balance, and supporting everyday metabolic homeostasis.</p>
        </div>
        <div class="organ-info-box">
            <h4>Related Biomarkers:</h4>
            <p>Blood Urea Nitrogen, Calcium, Chloride, Glomerular Filtration Rate (GFR), Phosphate, Potassium, Creatinine, Sodium</p>
        </div>
    </div>
</div>

<div class="organ-card organ-unused-note">*Unused / Unavailable Biomarkers</div>

</main>

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">
    <div class="footer-bg-bar"></div>
    <div class="footer-shapes">
        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>
    </div>
    <div class="footer-content-left">
        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>
        <p class="footer-copyright">© Mulkmed Healthcare, 2026. All Rights Reserved.</p>
    </div>
    <div class="page-number">Page 10 of 18</div>
</footer>

</div>


<!-- =======================================================
     PAGE 11
======================================================= -->

<div class="page-container">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>

<main class="page-content">

<div class="section-title-container">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_07_weekly_diet_plan/diet_plan_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:520px;">
        <h2>Weekly Diet Plan</h2>
    </div>
</div>

<div class="diet-table">
    <div class="diet-header">
        <div class="diet-header-cell day">Day</div>
        <div class="diet-header-cell breakfast">Breakfast</div>
        <div class="diet-header-cell lunch">Lunch</div>
        <div class="diet-header-cell dinner">Dinner</div>
    </div>

    <div class="diet-row">
        <div class="diet-cell day">Monday</div>
        <div class="diet-cell">3 scrambled eggs, 1 slice whole grain toast, 1 avocado</div>
        <div class="diet-cell">150g grilled chicken breast, 200g mixed salad with olive oil</div>
        <div class="diet-cell">200g salmon, 150g quinoa, 100g steamed broccoli</div>
    </div>

    <div class="diet-row">
        <div class="diet-cell day">Tuesday</div>
        <div class="diet-cell">200g greek yogurt, 50g mixed berries, 20g honey</div>
        <div class="diet-cell">150g turkey breast, 100g brown rice, 100g green beans</div>
        <div class="diet-cell">200g beef steak, 150g sweet potato, 100g asparagus</div>
    </div>

    <div class="diet-row">
        <div class="diet-cell day">Wednesday</div>
        <div class="diet-cell">2 boiled eggs, 1 slice whole grain toast, 1 orange</div>
        <div class="diet-cell">150g grilled shrimp, 200g mixed vegetables</div>
        <div class="diet-cell">200g grilled chicken, 150g couscous, 100g spinach</div>
    </div>

    <div class="diet-row">
        <div class="diet-cell day">Thursday</div>
        <div class="diet-cell">3 egg omelet with 50g cheese, 1 slice whole grain toast</div>
        <div class="diet-cell">150g pork tenderloin, 200g roasted vegetables</div>
        <div class="diet-cell">200g cod, 150g barley, 100g kale</div>
    </div>

    <div class="diet-row">
        <div class="diet-cell day">Friday</div>
        <div class="diet-cell">200g cottage cheese, 50g pineapple</div>
        <div class="diet-cell">150g grilled lamb, 100g quinoa, 100g mixed salad</div>
        <div class="diet-cell">200g chicken thighs, 150g brown rice, 100g brussels sprouts</div>
    </div>

    <div class="diet-row">
        <div class="diet-cell day">Saturday</div>
        <div class="diet-cell">2 fried eggs, 1 slice whole grain toast, 1 avocado</div>
        <div class="diet-cell">150g tuna salad, 100g chickpeas</div>
        <div class="diet-cell">200g grilled steak, 150g mashed potatoes, 100g green beans</div>
    </div>

    <div class="diet-row">
        <div class="diet-cell day">Sunday</div>
        <div class="diet-cell">200g greek yogurt, 50g granola, 20g honey</div>
        <div class="diet-cell">150g chicken breast, 200g mixed salad with olive oil</div>
        <div class="diet-cell">200g shrimp stir-fry with 150g rice</div>
    </div>
</div>

</main>

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">
    <div class="footer-bg-bar"></div>
    <div class="footer-shapes">
        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>
    </div>
    <div class="footer-content-left">
        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>
        <p class="footer-copyright">© Mulkmed Healthcare, 2026. All Rights Reserved.</p>
    </div>
    <div class="page-number">Page 11 of 18</div>
</footer>

</div>


<!-- =======================================================
     PAGE 12 — Weekly Exercise Plan (SVG chevron layout)
======================================================= -->

<div class="page-container page-exercise-plan">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>

<main class="page-content">

<div class="section-title-container">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_08_weekly_exercise_plan/exercise_plan_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:520px;">
        <h2>Weekly Exercise Plan</h2>
    </div>
</div>

<div class="wep-list">

    {{-- Monday --}}
    <article class="wep-row theme-mon">
        <svg class="wep-frame" viewBox="0 0 710 100" preserveAspectRatio="none" aria-hidden="true">
            <defs>
                <linearGradient id="g-mon" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0" stop-color="#cfe0f2"/>
                    <stop offset="0.42" stop-color="#cfe0f2"/>
                    <stop offset="1" stop-color="#ffffff"/>
                </linearGradient>
                <linearGradient id="w-mon" x1="170" y1="0" x2="230" y2="0" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stop-color="#cfe0f2"/>
                    <stop offset="1" stop-color="#ffffff"/>
                </linearGradient>
            </defs>
            <path d="M8,1 H684 L708.5,50 L684,99 H8 C3.2,99 1,96.8 1,91 V9 C1,3.2 3.2,1 8,1 Z" fill="url(#g-mon)"/>
            <path d="M181,28 H332 L360,50 L332,99 H168 L192,50 Z" fill="url(#w-mon)"/>
            <path d="M8,1 H684 L708.5,50 L684,99 H8 C3.2,99 1,96.8 1,91 V9 C1,3.2 3.2,1 8,1 Z" fill="none" stroke="#9eb6cc" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M168,1 L192,50 L168,99" fill="none" stroke="#9eb6cc" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M181,28 H684" fill="none" stroke="#9eb6cc" stroke-width="1.15"/>
            <path d="M332,28 L360,50 L332,99" fill="none" stroke="#9eb6cc" stroke-width="1.15" stroke-linejoin="round"/>
            <path d="M192,50 H360" fill="none" stroke="#9eb6cc" stroke-width="1.15"/>
        </svg>
        <div class="wep-grid">
            <div class="wep-col-day">
                <div class="wep-day-name">Monday</div>
                <div class="wep-icon">
                    <img src="{{ asset('asset/image/v3_page_wise/v3_exercise_images/images/brisk_walking.png') }}" alt="" width="58" height="58">
                </div>
            </div>
            <div class="wep-col-act">
                <div class="wep-act-name">Brisk walking</div>
                <div class="wep-act-body">
                    <div class="wep-sets">Sets - 1</div>
                    <div class="wep-dur">Duration - 30 minutes</div>
                </div>
            </div>
            <div class="wep-col-rec">
                <div class="wep-rec-title">Recommendation</div>
                <p class="wep-rec-text">Maintain a pace that elevates heart rate but allows for conversation.</p>
            </div>
        </div>
    </article>

    {{-- Tuesday --}}
    <article class="wep-row theme-tue">
        <svg class="wep-frame" viewBox="0 0 710 100" preserveAspectRatio="none" aria-hidden="true">
            <defs>
                <linearGradient id="g-tue" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0" stop-color="#cfe9dc"/>
                    <stop offset="0.42" stop-color="#cfe9dc"/>
                    <stop offset="1" stop-color="#ffffff"/>
                </linearGradient>
                <linearGradient id="w-tue" x1="170" y1="0" x2="230" y2="0" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stop-color="#cfe9dc"/>
                    <stop offset="1" stop-color="#ffffff"/>
                </linearGradient>
            </defs>
            <path d="M8,1 H684 L708.5,50 L684,99 H8 C3.2,99 1,96.8 1,91 V9 C1,3.2 3.2,1 8,1 Z" fill="url(#g-tue)"/>
            <path d="M181,28 H332 L360,50 L332,99 H168 L192,50 Z" fill="url(#w-tue)"/>
            <path d="M8,1 H684 L708.5,50 L684,99 H8 C3.2,99 1,96.8 1,91 V9 C1,3.2 3.2,1 8,1 Z" fill="none" stroke="#9cc4b0" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M168,1 L192,50 L168,99" fill="none" stroke="#9cc4b0" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M181,28 H684" fill="none" stroke="#9cc4b0" stroke-width="1.15"/>
            <path d="M332,28 L360,50 L332,99" fill="none" stroke="#9cc4b0" stroke-width="1.15" stroke-linejoin="round"/>
            <path d="M192,50 H360" fill="none" stroke="#9cc4b0" stroke-width="1.15"/>
        </svg>
        <div class="wep-grid">
            <div class="wep-col-day">
                <div class="wep-day-name">Tuesday</div>
                <div class="wep-icon">
                    <img src="{{ asset('asset/image/v3_page_wise/v3_exercise_images/images/strength_training.png') }}" alt="" width="58" height="58">
                </div>
            </div>
            <div class="wep-col-act">
                <div class="wep-act-name">Strength Training</div>
                <div class="wep-act-body">
                    <div class="wep-sets">Sets - 3</div>
                    <div class="wep-dur">Duration - 20 minutes</div>
                </div>
            </div>
            <div class="wep-col-rec">
                <div class="wep-rec-title">Recommendation</div>
                <p class="wep-rec-text">Focus on squats, push-ups, and lunges. Rest 1 minute between sets.</p>
            </div>
        </div>
    </article>

    {{-- Wednesday --}}
    <article class="wep-row theme-wed">
        <svg class="wep-frame" viewBox="0 0 710 100" preserveAspectRatio="none" aria-hidden="true">
            <defs>
                <linearGradient id="g-wed" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0" stop-color="#e6daf2"/>
                    <stop offset="0.42" stop-color="#e6daf2"/>
                    <stop offset="1" stop-color="#ffffff"/>
                </linearGradient>
                <linearGradient id="w-wed" x1="170" y1="0" x2="230" y2="0" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stop-color="#e6daf2"/>
                    <stop offset="1" stop-color="#ffffff"/>
                </linearGradient>
            </defs>
            <path d="M8,1 H684 L708.5,50 L684,99 H8 C3.2,99 1,96.8 1,91 V9 C1,3.2 3.2,1 8,1 Z" fill="url(#g-wed)"/>
            <path d="M181,28 H332 L360,50 L332,99 H168 L192,50 Z" fill="url(#w-wed)"/>
            <path d="M8,1 H684 L708.5,50 L684,99 H8 C3.2,99 1,96.8 1,91 V9 C1,3.2 3.2,1 8,1 Z" fill="none" stroke="#c0aed4" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M168,1 L192,50 L168,99" fill="none" stroke="#c0aed4" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M181,28 H684" fill="none" stroke="#c0aed4" stroke-width="1.15"/>
            <path d="M332,28 L360,50 L332,99" fill="none" stroke="#c0aed4" stroke-width="1.15" stroke-linejoin="round"/>
            <path d="M192,50 H360" fill="none" stroke="#c0aed4" stroke-width="1.15"/>
        </svg>
        <div class="wep-grid">
            <div class="wep-col-day">
                <div class="wep-day-name">Wednesday</div>
                <div class="wep-icon">
                    <img src="{{ asset('asset/image/v3_page_wise/v3_exercise_images/images/cycling.png') }}" alt="" width="58" height="58">
                </div>
            </div>
            <div class="wep-col-act">
                <div class="wep-act-name">Cycling</div>
                <div class="wep-act-body">
                    <div class="wep-sets">Sets - 1</div>
                    <div class="wep-dur">Duration - 30 minutes</div>
                </div>
            </div>
            <div class="wep-col-rec">
                <div class="wep-rec-title">Recommendation</div>
                <p class="wep-rec-text">Keep a steady pace, aiming for a heart rate of 60–70% of max.</p>
            </div>
        </div>
    </article>

    {{-- Thursday --}}
    <article class="wep-row theme-thu">
        <svg class="wep-frame" viewBox="0 0 710 100" preserveAspectRatio="none" aria-hidden="true">
            <defs>
                <linearGradient id="g-thu" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0" stop-color="#d0ecef"/>
                    <stop offset="0.42" stop-color="#d0ecef"/>
                    <stop offset="1" stop-color="#ffffff"/>
                </linearGradient>
                <linearGradient id="w-thu" x1="170" y1="0" x2="230" y2="0" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stop-color="#d0ecef"/>
                    <stop offset="1" stop-color="#ffffff"/>
                </linearGradient>
            </defs>
            <path d="M8,1 H684 L708.5,50 L684,99 H8 C3.2,99 1,96.8 1,91 V9 C1,3.2 3.2,1 8,1 Z" fill="url(#g-thu)"/>
            <path d="M181,28 H332 L360,50 L332,99 H168 L192,50 Z" fill="url(#w-thu)"/>
            <path d="M8,1 H684 L708.5,50 L684,99 H8 C3.2,99 1,96.8 1,91 V9 C1,3.2 3.2,1 8,1 Z" fill="none" stroke="#9cc4c9" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M168,1 L192,50 L168,99" fill="none" stroke="#9cc4c9" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M181,28 H684" fill="none" stroke="#9cc4c9" stroke-width="1.15"/>
            <path d="M332,28 L360,50 L332,99" fill="none" stroke="#9cc4c9" stroke-width="1.15" stroke-linejoin="round"/>
            <path d="M192,50 H360" fill="none" stroke="#9cc4c9" stroke-width="1.15"/>
        </svg>
        <div class="wep-grid">
            <div class="wep-col-day">
                <div class="wep-day-name">Thursday</div>
                <div class="wep-icon">
                    <img src="{{ asset('asset/image/v3_page_wise/v3_exercise_images/images/restorative_yoga.png') }}" alt="" width="58" height="58">
                </div>
            </div>
            <div class="wep-col-act">
                <div class="wep-act-name">Yoga</div>
                <div class="wep-act-body">
                    <div class="wep-sets">Sets - 1</div>
                    <div class="wep-dur">Duration - 30 minutes</div>
                </div>
            </div>
            <div class="wep-col-rec">
                <div class="wep-rec-title">Recommendation</div>
                <p class="wep-rec-text">Focus on breathing and flexibility; hold each pose for 30 seconds.</p>
            </div>
        </div>
    </article>

    {{-- Friday --}}
    <article class="wep-row theme-fri">
        <svg class="wep-frame" viewBox="0 0 710 100" preserveAspectRatio="none" aria-hidden="true">
            <defs>
                <linearGradient id="g-fri" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0" stop-color="#f8d9cc"/>
                    <stop offset="0.42" stop-color="#f8d9cc"/>
                    <stop offset="1" stop-color="#ffffff"/>
                </linearGradient>
                <linearGradient id="w-fri" x1="170" y1="0" x2="230" y2="0" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stop-color="#f8d9cc"/>
                    <stop offset="1" stop-color="#ffffff"/>
                </linearGradient>
            </defs>
            <path d="M8,1 H684 L708.5,50 L684,99 H8 C3.2,99 1,96.8 1,91 V9 C1,3.2 3.2,1 8,1 Z" fill="url(#g-fri)"/>
            <path d="M181,28 H332 L360,50 L332,99 H168 L192,50 Z" fill="url(#w-fri)"/>
            <path d="M8,1 H684 L708.5,50 L684,99 H8 C3.2,99 1,96.8 1,91 V9 C1,3.2 3.2,1 8,1 Z" fill="none" stroke="#d4b09f" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M168,1 L192,50 L168,99" fill="none" stroke="#d4b09f" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M181,28 H684" fill="none" stroke="#d4b09f" stroke-width="1.15"/>
            <path d="M332,28 L360,50 L332,99" fill="none" stroke="#d4b09f" stroke-width="1.15" stroke-linejoin="round"/>
            <path d="M192,50 H360" fill="none" stroke="#d4b09f" stroke-width="1.15"/>
        </svg>
        <div class="wep-grid">
            <div class="wep-col-day">
                <div class="wep-day-name">Friday</div>
                <div class="wep-icon">
                    <img src="{{ asset('asset/image/v3_page_wise/v3_exercise_images/images/interval_running.png') }}" alt="" width="58" height="58">
                </div>
            </div>
            <div class="wep-col-act">
                <div class="wep-act-name">Interval training</div>
                <div class="wep-act-body">
                    <div class="wep-sets">Sets - 5</div>
                    <div class="wep-dur">Duration - 25 minutes</div>
                </div>
            </div>
            <div class="wep-col-rec">
                <div class="wep-rec-title">Recommendation</div>
                <p class="wep-rec-text">Alternate 1 minute of running with 2 minutes of walking.</p>
            </div>
        </div>
    </article>

    {{-- Saturday --}}
    <article class="wep-row theme-sat">
        <svg class="wep-frame" viewBox="0 0 710 100" preserveAspectRatio="none" aria-hidden="true">
            <defs>
                <linearGradient id="g-sat" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0" stop-color="#e4e6ea"/>
                    <stop offset="0.42" stop-color="#e4e6ea"/>
                    <stop offset="1" stop-color="#ffffff"/>
                </linearGradient>
                <linearGradient id="w-sat" x1="170" y1="0" x2="230" y2="0" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stop-color="#e4e6ea"/>
                    <stop offset="1" stop-color="#ffffff"/>
                </linearGradient>
            </defs>
            <path d="M8,1 H684 L708.5,50 L684,99 H8 C3.2,99 1,96.8 1,91 V9 C1,3.2 3.2,1 8,1 Z" fill="url(#g-sat)"/>
            <path d="M181,28 H332 L360,50 L332,99 H168 L192,50 Z" fill="url(#w-sat)"/>
            <path d="M8,1 H684 L708.5,50 L684,99 H8 C3.2,99 1,96.8 1,91 V9 C1,3.2 3.2,1 8,1 Z" fill="none" stroke="#b5b7bb" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M168,1 L192,50 L168,99" fill="none" stroke="#b5b7bb" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M181,28 H684" fill="none" stroke="#b5b7bb" stroke-width="1.15"/>
            <path d="M332,28 L360,50 L332,99" fill="none" stroke="#b5b7bb" stroke-width="1.15" stroke-linejoin="round"/>
            <path d="M192,50 H360" fill="none" stroke="#b5b7bb" stroke-width="1.15"/>
        </svg>
        <div class="wep-grid">
            <div class="wep-col-day">
                <div class="wep-day-name">Saturday</div>
                <div class="wep-icon">
                    <img src="{{ asset('asset/image/v3_page_wise/v3_exercise_images/images/swimming.png') }}" alt="" width="58" height="58">
                </div>
            </div>
            <div class="wep-col-act">
                <div class="wep-act-name">Swimming</div>
                <div class="wep-act-body">
                    <div class="wep-sets">Sets - 1</div>
                    <div class="wep-dur">Duration - 30 minutes</div>
                </div>
            </div>
            <div class="wep-col-rec">
                <div class="wep-rec-title">Recommendation</div>
                <p class="wep-rec-text">Swim at a comfortable pace, focusing on technique.</p>
            </div>
        </div>
    </article>

    {{-- Sunday --}}
    <article class="wep-row theme-sun">
        <svg class="wep-frame" viewBox="0 0 710 100" preserveAspectRatio="none" aria-hidden="true">
            <defs>
                <linearGradient id="g-sun" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0" stop-color="#d4eadc"/>
                    <stop offset="0.42" stop-color="#d4eadc"/>
                    <stop offset="1" stop-color="#ffffff"/>
                </linearGradient>
                <linearGradient id="w-sun" x1="170" y1="0" x2="230" y2="0" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stop-color="#d4eadc"/>
                    <stop offset="1" stop-color="#ffffff"/>
                </linearGradient>
            </defs>
            <path d="M8,1 H684 L708.5,50 L684,99 H8 C3.2,99 1,96.8 1,91 V9 C1,3.2 3.2,1 8,1 Z" fill="url(#g-sun)"/>
            <path d="M181,28 H332 L360,50 L332,99 H168 L192,50 Z" fill="url(#w-sun)"/>
            <path d="M8,1 H684 L708.5,50 L684,99 H8 C3.2,99 1,96.8 1,91 V9 C1,3.2 3.2,1 8,1 Z" fill="none" stroke="#a3c4b0" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M168,1 L192,50 L168,99" fill="none" stroke="#a3c4b0" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M181,28 H684" fill="none" stroke="#a3c4b0" stroke-width="1.15"/>
            <path d="M332,28 L360,50 L332,99" fill="none" stroke="#a3c4b0" stroke-width="1.15" stroke-linejoin="round"/>
            <path d="M192,50 H360" fill="none" stroke="#a3c4b0" stroke-width="1.15"/>
        </svg>
        <div class="wep-grid">
            <div class="wep-col-day">
                <div class="wep-day-name">Sunday</div>
                <div class="wep-icon">
                    <img src="{{ asset('asset/image/v3_page_wise/v3_exercise_images/images/rest_day.png') }}" alt="" width="58" height="58">
                </div>
            </div>
            <div class="wep-col-act">
                <div class="wep-act-name">Rest and recovery</div>
                <div class="wep-act-body">
                    <div class="wep-sets">Sets - 1</div>
                    <div class="wep-dur">Duration - 0 minutes</div>
                </div>
            </div>
            <div class="wep-col-rec">
                <div class="wep-rec-title">Recommendation</div>
                <p class="wep-rec-text">Focus on hydration and nutrition; consider light stretching.</p>
            </div>
        </div>
    </article>

</div>

</main>

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">
    <div class="footer-bg-bar"></div>
    <div class="footer-shapes">
        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>
    </div>
    <div class="footer-content-left">
        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>
        <p class="footer-copyright">© Mulkmed Healthcare, 2026. All Rights Reserved.</p>
    </div>
    <div class="page-number">Page 12 of 18</div>
</footer>

</div>


<!-- =======================================================
     PAGE 13
======================================================= -->

<div class="page-container">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>

<main class="page-content">

<div class="section-title-container">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_09_lifestyle_recommendations_1/lifestyle_recommendations_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:520px;">
        <h2>Lifestyle Recommendations</h2>
    </div>
</div>

<div class="lifestyle-card">
    <div class="lifestyle-section">
        <h3>
            <img class="lifestyle-section-icon" src="{{ asset('asset/image/v3_page_wise/page_10_lifestyle_recommendations_2/eating-behaviour-mono.svg') }}" alt="" width="22" height="22">
            Calorie Restriction
        </h3>
        <ul>
            <li>Implement a structured meal schedule with three main meals and two healthy snacks, ensuring at least 4-5 hours between meals to promote calorie restriction.</li>
            <li>Practice mindful eating by slowing down during meals, focusing on portion sizes, and avoiding distractions to help recognize hunger and fullness cues.</li>
            <li>Incorporate hydration strategies by drinking a glass of water before meals to help control appetite and reduce overall calorie intake.</li>
        </ul>
    </div>

    <div class="lifestyle-section">
        <h3>
            <img class="lifestyle-section-icon" src="{{ asset('asset/image/v3_page_wise/page_09_lifestyle_recommendations_1/hydration-cellular-health-mono.svg') }}" alt="" width="22" height="22">
            Hydration
        </h3>
        <ul>
            <li>Carry a reusable water bottle to ensure you have access to water throughout the day</li>
            <li>Set reminders on your phone to drink water at regular intervals, aiming for at least 8-10 cups daily</li>
            <li>Incorporate hydrating activities into your routine, such as taking short breaks to drink water during work or while engaging in hobbies.</li>
        </ul>
    </div>

    <div class="lifestyle-section">
        <h3>
            <img class="lifestyle-section-icon" src="{{ asset('asset/image/v3_page_wise/page_10_lifestyle_recommendations_2/recovery-energy-mono.svg') }}" alt="" width="22" height="22">
            Hygiene
        </h3>
        <ul>
            <li>Maintain a consistent oral hygiene routine by brushing twice daily and flossing to reduce inflammation in oral tissues.</li>
            <li>Replace your toothbrush every 3-4 months or sooner if bristles are frayed to ensure effective cleaning.</li>
            <li>Schedule regular dental check-ups and cleanings to monitor and address any potential oral health issues.</li>
        </ul>
    </div>

    <div class="lifestyle-section">
        <h3>
            <img class="lifestyle-section-icon" src="{{ asset('asset/image/v3_page_wise/page_09_lifestyle_recommendations_1/sleep-restoration-mono.svg') }}" alt="" width="22" height="22">
            Sleep
        </h3>
        <ul>
            <li>Establish a consistent sleep schedule by going to bed and waking up at the same time every day to regulate your body's internal clock.</li>
            <li>Create a relaxing bedtime routine that includes activities such as reading, meditation, or deep breathing exercises to help reduce stress and promote better sleep quality.</li>
            <li>Limit screen time at least one hour before bed to minimize blue light exposure, which can interfere with melatonin production and disrupt sleep.</li>
        </ul>
    </div>

    <div class="lifestyle-section">
        <h3>
            <img class="lifestyle-section-icon" src="{{ asset('asset/image/v3_page_wise/page_09_lifestyle_recommendations_1/emotional-wellbeing-mono.svg') }}" alt="" width="22" height="22">
            Well-Being
        </h3>
        <ul>
            <li>Incorporate mindfulness practices such as meditation or deep breathing exercises to reduce stress levels.</li>
            <li>Establish a consistent sleep routine to improve overall well-being and support better glucose management.</li>
            <li>Engage in social activities or hobbies that promote relaxation and joy, helping to alleviate stress and improve emotional health.</li>
        </ul>
    </div>
</div>

</main>

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">
    <div class="footer-bg-bar"></div>
    <div class="footer-shapes">
        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>
    </div>
    <div class="footer-content-left">
        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>
        <p class="footer-copyright">© Mulkmed Healthcare, 2026. All Rights Reserved.</p>
    </div>
    <div class="page-number">Page 13 of 18</div>
</footer>

</div>


<!-- =======================================================
     PAGE 14
======================================================= -->

<div class="page-container">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>

<main class="page-content">

<div class="section-title-container">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_11_supplements_biomarkers_overview/supplements_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:520px;">
        <h2>Recommended Supplements</h2>
    </div>
</div>

<div class="supplement-grid">
    <div class="supplement-card">
        <h3>Berberine</h3>
        <div class="benefits-box">
            <h4>Benefits</h4>
            <p>Helps lower blood glucose levels and improve insulin sensitivity.</p>
        </div>
    </div>
    <div class="supplement-card">
        <h3>Alpha-lipoic acid</h3>
        <div class="benefits-box">
            <h4>Benefits</h4>
            <p>May enhance glucose uptake and reduce oxidative stress.</p>
        </div>
    </div>
    <div class="supplement-card">
        <h3>Cinnamon extract</h3>
        <div class="benefits-box">
            <h4>Benefits</h4>
            <p>Can improve insulin sensitivity and lower fasting blood glucose.</p>
        </div>
    </div>
    <div class="supplement-card">
        <h3>Omega-3 fatty acids</h3>
        <div class="benefits-box">
            <h4>Benefits</h4>
            <p>Helps lower ldl cholesterol and supports cardiovascular health.</p>
        </div>
    </div>
    <div class="supplement-card">
        <h3>Plant sterols</h3>
        <div class="benefits-box">
            <h4>Benefits</h4>
            <p>Reduces ldl cholesterol absorption in the intestines.</p>
        </div>
    </div>
    <div class="supplement-card">
        <h3>Coenzyme q10 (coq10)</h3>
        <div class="benefits-box">
            <h4>Benefits</h4>
            <p>Supports heart health and may improve cholesterol levels.</p>
        </div>
    </div>
</div>

<div class="section-title-container" style="margin-top:6px;">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_11_supplements_biomarkers_overview/biomarkers_overview_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:520px;">
        <h2>Biomarkers overview</h2>
    </div>
</div>

<div class="biomarker-section-card">
    <h3 class="biomarker-section-title">Cholesterol Status</h3>
    <div class="biomarker-grid">
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>HDL Cholesterol</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 40.0 - 999.0</span>
                    <span>Unit: mg/dl</span>
                </div>
            </div>
            <div class="value-box green">44.0</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>LDL Cholesterol</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 0 - 150.0</span>
                    <span>Unit: mg/dl</span>
                </div>
            </div>
            <div class="value-box green">138.0</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Triglycerides</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 0 - 200.0</span>
                    <span>Unit: mg/dl</span>
                </div>
            </div>
            <div class="value-box green">46.0</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Total Cholesterol</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 0 - 200.0</span>
                    <span>Unit: mg/dl</span>
                </div>
            </div>
            <div class="value-box green">193.0</div>
        </div>
    </div>
</div>

</main>

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">
    <div class="footer-bg-bar"></div>
    <div class="footer-shapes">
        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>
    </div>
    <div class="footer-content-left">
        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>
        <p class="footer-copyright">© Mulkmed Healthcare, 2026. All Rights Reserved.</p>
    </div>
    <div class="page-number">Page 14 of 18</div>
</footer>

</div>


<!-- =======================================================
     PAGE 15
======================================================= -->

<div class="page-container">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>

<main class="page-content">

<div class="section-title-container">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_11_supplements_biomarkers_overview/biomarkers_overview_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:520px;">
        <h2>Biomarkers overview</h2>
    </div>
</div>

<div class="biomarker-section-card">
    <h3 class="biomarker-section-title">Red Blood Cells Status</h3>
    <div class="biomarker-grid">
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Hematocrit</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 37.0 - 54.0</span>
                    <span>Unit: %</span>
                </div>
            </div>
            <div class="value-box green">43.7</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Hemoglobin</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 12.0 - 16.0</span>
                    <span>Unit: g/dl</span>
                </div>
            </div>
            <div class="value-box green">14.6</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Mean Corpuscular Hemoglobin</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 27.0 - 34.0</span>
                    <span>Unit: pg</span>
                </div>
            </div>
            <div class="value-box green">28.0</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>MCHC</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 31.0 - 36.0</span>
                    <span>Unit: g/dl</span>
                </div>
            </div>
            <div class="value-box green">33.4</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Mean Corpuscular Volume</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 82.0 - 100.0</span>
                    <span>Unit: fl</span>
                </div>
            </div>
            <div class="value-box green">83.7</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Red Blood Cells</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 4.5 - 5.5</span>
                    <span>Unit: 10^6/mm3</span>
                </div>
            </div>
            <div class="value-box green">5.22</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Red Cell Distribution Width</h4>
                    <span class="status-pill gray">Not Available</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: NA</span>
                    <span>Unit: NA</span>
                </div>
            </div>
            <div class="value-box gray">NA</div>
        </div>
    </div>
</div>

<div class="biomarker-section-card">
    <h3 class="biomarker-section-title">White Blood Cells Status</h3>
    <div class="biomarker-grid">
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Basophils, %</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 0 - 15.0</span>
                    <span>Unit: %</span>
                </div>
            </div>
            <div class="value-box green">0.3</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Eosinophils, %</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 0 - 8.0</span>
                    <span>Unit: %</span>
                </div>
            </div>
            <div class="value-box green">1.5</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Lymphocytes, %</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 20.0 - 45.0</span>
                    <span>Unit: %</span>
                </div>
            </div>
            <div class="value-box green">38.1</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Monocytes, %</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 3.0 - 11.0</span>
                    <span>Unit: %</span>
                </div>
            </div>
            <div class="value-box green">4.3</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Neutrophils, %</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 40.0 - 74.0</span>
                    <span>Unit: %</span>
                </div>
            </div>
            <div class="value-box green">55.8</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>White Blood Cell</h4>
                    <span class="status-pill red">Low</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 4.0 - 11.0</span>
                    <span>Unit: 10^6/mm3</span>
                </div>
            </div>
            <div class="value-box red">3.28</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Atypical Lymphocyte</h4>
                    <span class="status-pill gray">Not Available</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: NA</span>
                    <span>Unit: NA</span>
                </div>
            </div>
            <div class="value-box gray">NA</div>
        </div>
    </div>
</div>

<div class="biomarker-section-card" style="margin-bottom:0;">
    <h3 class="biomarker-section-title">Iron Status</h3>
    <div class="biomarker-grid">
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Ferritin</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 10.0 - 250.0</span>
                    <span>Unit: ng/ml</span>
                </div>
            </div>
            <div class="value-box green">163.6</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Iron</h4>
                    <span class="status-pill gray">Not Available</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: NA</span>
                    <span>Unit: NA</span>
                </div>
            </div>
            <div class="value-box gray">NA</div>
        </div>
    </div>
</div>

</main>

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">
    <div class="footer-bg-bar"></div>
    <div class="footer-shapes">
        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>
    </div>
    <div class="footer-content-left">
        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>
        <p class="footer-copyright">© Mulkmed Healthcare, 2026. All Rights Reserved.</p>
    </div>
    <div class="page-number">Page 15 of 18</div>
</footer>

</div>


<!-- =======================================================
     PAGE 16
======================================================= -->

<div class="page-container">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>

<main class="page-content">

<div class="section-title-container">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_11_supplements_biomarkers_overview/biomarkers_overview_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:520px;">
        <h2>Biomarkers overview</h2>
    </div>
</div>

<div class="biomarker-section-card">
    <h3 class="biomarker-section-title">Clotting Status</h3>
    <div class="biomarker-grid">
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Platelets</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 130 - 450.0</span>
                    <span>Unit: 10^6/mm3</span>
                </div>
            </div>
            <div class="value-box green">210.0</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Erythrocyte Sedimentation Rate</h4>
                    <span class="status-pill gray">Not Available</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: NA</span>
                    <span>Unit: NA</span>
                </div>
            </div>
            <div class="value-box gray">NA</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Mean Platelet Volume</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 6.5 - 12.0</span>
                    <span>Unit: fl</span>
                </div>
            </div>
            <div class="value-box green">8.8</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Platelet Distribution Width</h4>
                    <span class="status-pill gray">Not Available</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: NA</span>
                    <span>Unit: NA</span>
                </div>
            </div>
            <div class="value-box gray">NA</div>
        </div>
    </div>
</div>

<div class="biomarker-section-card">
    <h3 class="biomarker-section-title">Minerals Status</h3>
    <div class="biomarker-grid">
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Calcium</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 8.8 - 10.2</span>
                    <span>Unit: mg/dl</span>
                </div>
            </div>
            <div class="value-box green">9.3</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Chloride</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 98.0 - 107.0</span>
                    <span>Unit: mmol/l</span>
                </div>
            </div>
            <div class="value-box green">103.0</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Potassium</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 3.5 - 5.1</span>
                    <span>Unit: mmol/l</span>
                </div>
            </div>
            <div class="value-box green">4.26</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Sodium</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 136.0 - 145.0</span>
                    <span>Unit: mmol/l</span>
                </div>
            </div>
            <div class="value-box green">141.0</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Phosphorous</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 2.7 - 4.5</span>
                    <span>Unit: mg/dl</span>
                </div>
            </div>
            <div class="value-box green">4.3</div>
        </div>
    </div>
</div>

<div class="biomarker-section-card" style="margin-bottom:0;">
    <h3 class="biomarker-section-title">Proteins Status</h3>
    <div class="biomarker-grid">
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Albumin</h4>
                    <span class="status-pill gray">Not Available</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: NA</span>
                    <span>Unit: NA</span>
                </div>
            </div>
            <div class="value-box gray">NA</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Total Globulin</h4>
                    <span class="status-pill gray">Not Available</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: NA</span>
                    <span>Unit: NA</span>
                </div>
            </div>
            <div class="value-box gray">NA</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Total Protein</h4>
                    <span class="status-pill gray">Not Available</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: NA</span>
                    <span>Unit: NA</span>
                </div>
            </div>
            <div class="value-box gray">NA</div>
        </div>
    </div>
</div>

</main>

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">
    <div class="footer-bg-bar"></div>
    <div class="footer-shapes">
        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>
    </div>
    <div class="footer-content-left">
        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>
        <p class="footer-copyright">© Mulkmed Healthcare, 2026. All Rights Reserved.</p>
    </div>
    <div class="page-number">Page 16 of 18</div>
</footer>

</div>


<!-- =======================================================
     PAGE 17
======================================================= -->

<div class="page-container">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>

<main class="page-content">

<div class="section-title-container">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_11_supplements_biomarkers_overview/biomarkers_overview_subheader_icon.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:520px;">
        <h2>Biomarkers overview</h2>
    </div>
</div>

<div class="biomarker-section-card">
    <h3 class="biomarker-section-title">Other Status</h3>
    <div class="biomarker-grid">
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Alpha-Fetoprotein</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 0 - 8.1</span>
                    <span>Unit: ng/ml</span>
                </div>
            </div>
            <div class="value-box green">2.13</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Alpha-Amylase</h4>
                    <span class="status-pill gray">Not Available</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: NA</span>
                    <span>Unit: NA</span>
                </div>
            </div>
            <div class="value-box gray">NA</div>
        </div>
    </div>
</div>

<div class="biomarker-section-card">
    <h3 class="biomarker-section-title">Kidney Status</h3>
    <div class="biomarker-grid">
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Blood Urea Nitrogen</h4>
                    <span class="status-pill red">High</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 3.61 - 10.56</span>
                    <span>Unit: mg/dl</span>
                </div>
            </div>
            <div class="value-box red">13.6</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Uric Acid</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 2.4 - 5.7</span>
                    <span>Unit: mg/dl</span>
                </div>
            </div>
            <div class="value-box green">3.7</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Creatinine</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 0.6 - 1.1</span>
                    <span>Unit: mg/dl</span>
                </div>
            </div>
            <div class="value-box green">0.81</div>
        </div>
    </div>
</div>

<div class="biomarker-section-card">
    <h3 class="biomarker-section-title">Liver Status</h3>
    <div class="biomarker-grid">
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Alkaline Phosphatase</h4>
                    <span class="status-pill gray">Not Available</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: NA</span>
                    <span>Unit: NA</span>
                </div>
            </div>
            <div class="value-box gray">NA</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Direct Bilirubin</h4>
                    <span class="status-pill gray">Not Available</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: NA</span>
                    <span>Unit: NA</span>
                </div>
            </div>
            <div class="value-box gray">NA</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Alanine Transaminase</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 0 - 33.0</span>
                    <span>Unit: u/l</span>
                </div>
            </div>
            <div class="value-box green">22.0</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Total Bilirubin</h4>
                    <span class="status-pill gray">Not Available</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: NA</span>
                    <span>Unit: NA</span>
                </div>
            </div>
            <div class="value-box gray">NA</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Aspartate Aminotransferase</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 0 - 32.0</span>
                    <span>Unit: u/l</span>
                </div>
            </div>
            <div class="value-box green">25.0</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Gamma-GT</h4>
                    <span class="status-pill gray">Not Available</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: NA</span>
                    <span>Unit: NA</span>
                </div>
            </div>
            <div class="value-box gray">NA</div>
        </div>
    </div>
</div>

<div class="biomarker-section-card" style="margin-bottom:0;">
    <h3 class="biomarker-section-title">Glucose Status</h3>
    <div class="biomarker-grid">
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Glucose Fasting</h4>
                    <span class="status-pill red">High</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 60.0 - 100.0</span>
                    <span>Unit: mg/dl</span>
                </div>
            </div>
            <div class="value-box red">102.0</div>
        </div>
        <div class="biomarker-item">
            <div class="biomarker-info">
                <div class="biomarker-name-row">
                    <h4>Hemoglobin A1c</h4>
                    <span class="status-pill green">Within Range</span>
                </div>
                <div class="biomarker-meta">
                    <span>Ref. Range: 4.0 - 6.0</span>
                    <span>Unit: %</span>
                </div>
            </div>
            <div class="value-box green">5.6</div>
        </div>
    </div>
</div>

</main>

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">
    <div class="footer-bg-bar"></div>
    <div class="footer-shapes">
        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>
    </div>
    <div class="footer-content-left">
        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>
        <p class="footer-copyright">© Mulkmed Healthcare, 2026. All Rights Reserved.</p>
    </div>
    <div class="page-number">Page 17 of 18</div>
</footer>

</div>


<!-- =======================================================
     PAGE 18
======================================================= -->

<div class="page-container">

<header class="header">
<table class="header-table" width="100%" cellpadding="0" cellspacing="0"><tr>
    <td class="left">
        <div class="logo-container">
            <img class="header-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="120" height="52">
        </div>
    </td>
    <td>
        <img class="header-report-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/blood_age_v3_logo.png') }}" alt="Blood Age Report" width="70" height="70">
        <p class="header-title">Blood Age Report</p>
    </td>
    <td class="right" aria-hidden="true"></td>
</tr></table>
</header>

<main class="page-content">

<div class="section-title-container">
    <div class="title-icon">
        <img src="{{ asset('asset/image/v3_page_wise/page_12_warning/warning_title_subheader_logo.png') }}" alt="" width="34" height="34">
    </div>
    <div class="title-bar" style="width:260px;">
        <h2>Warning</h2>
    </div>
</div>

<div class="warning-container">
    <h3>Tips to induce on a daily basis</h3>
    <div class="warning-box">
        The information in this section cannot be used for self-diagnosis or self-medication and is for informational purposes only. Deep Longevity does not provide specific medical advice or medical assistance. Always seek the advice of a trained health professional for medical consultation, diagnosis, or treatment.
    </div>
    <div class="company-info">
        <h2>Deep Longevity Limited</h2>
        <div class="company-details-grid">
            <div class="company-details-left">
                <span>8/F, Henley Building</span>
                <span>5 Queen's Road Central,</span>
                <span>Central, Hong Kong</span>
            </div>
            <div class="company-details-right">
                <span>+852 (0) 131 34-90</span>
                <span>www.deeplongevity.com</span>
                <span>info@deeplongevity.com</span>
            </div>
        </div>
    </div>
</div>

</main>

<div class="footer-slot" aria-hidden="true"></div>
<footer class="footer">
    <div class="footer-bg-bar"></div>
    <div class="footer-shapes">
        <div class="shape-layer layer-4"></div>
        <div class="shape-layer layer-3"></div>
        <div class="shape-layer layer-2"></div>
        <div class="shape-layer layer-1"></div>
    </div>
    <div class="footer-content-left">
        <div class="footer-logo">
            <img class="footer-company-logo" src="{{ asset('asset/image/v3_page_wise/_common_header_footer/logo_grey_text.jpeg') }}" alt="Mulk Longevity" width="110" height="40">
        </div>
        <p class="footer-copyright">© Mulkmed Healthcare, 2026. All Rights Reserved.</p>
    </div>
    <div class="page-number">Page 18 of 18</div>
</footer>

</div>


</body>
</html>