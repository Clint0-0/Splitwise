#!/usr/bin/env python3
"""Accessibility scan of every screen (desktop + mobile) with axe-core (WCAG 2.0/2.1 A, AA, best practice).
Get axe:  npm pack axe-core@4 && tar -xzf axe-core-*.tgz   (then pass package/axe.min.js)
Usage:    python3 tools/a11y_scan.py path/to/axe.min.js [http://127.0.0.1:8000]
"""
import sys, json
import os; sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from playwright.sync_api import sync_playwright
import screenshots as S
AXE = open(sys.argv[1] if len(sys.argv) > 1 else 'axe.min.js').read()
base = sys.argv[2] if len(sys.argv) > 2 else 'http://127.0.0.1:8000'
total = {}
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/usr/bin/google-chrome')
    for vpname, vp in [('desktop', (1366, 900)), ('mobile', (390, 844))]:
        sessions = {}
        for name, user, path in S.PAGES:
            if user not in sessions:
                ctx = b.new_context(viewport={'width': vp[0], 'height': vp[1]}, bypass_csp=True); pg = ctx.new_page()  # bypass_csp: axe is injected inline, the app's CSP would block it
                if user:
                    pg.goto(base + '/login'); pg.fill('#login', user); pg.fill('#password', 'Password@123'); pg.click('button[type=submit]'); pg.wait_for_load_state('networkidle')
                sessions[user] = pg
            pg = sessions[user]
            pg.goto(base + path); pg.wait_for_load_state('networkidle'); pg.wait_for_timeout(300)
            pg.add_script_tag(content=AXE)
            res = pg.evaluate("async () => (await axe.run(document, {runOnly: ['wcag2a','wcag2aa','wcag21aa','best-practice']})).violations.map(v => ({id: v.id, impact: v.impact, n: v.nodes.length, t: v.nodes.slice(0,2).map(n => n.target.join(' ')), help: v.help}))")
            for v in res:
                key = (v['id'], v['help'])
                total.setdefault(key, []).append(f"{vpname}-{name}({v['n']}): {v['t']}")
    b.close()
if not total: print('axe: 0 violations on all screens')
for (i, h), where in total.items():
    print(f"\n[{i}] {h}\n  " + "\n  ".join(where[:6]))
