#!/usr/bin/env python3
"""Screenshot every SplitWise+ screen (desktop + mobile) for design review and the report.

Needs: Python Playwright and a Chrome/Chromium; the app running with the sample data.
Usage: python3 tools/screenshots.py [--base http://127.0.0.1:8000] [--out docs/screenshots] [--only dashboard,groups]
"""
import argparse, os, re
from playwright.sync_api import sync_playwright

PAGES = [  # (name, user, path)
    ('login', None, '/login'),
    ('register', None, '/register'),
    ('dashboard', 'priya', '/dashboard'),
    ('groups', 'priya', '/groups'),
    ('group', 'aarav', '/groups/1'),
    ('expense-form', 'aarav', '/expenses/create?group=1'),
    ('expense', 'aarav', '/expenses/3'),
    ('expenses', 'priya', '/expenses'),
    ('balances', 'priya', '/balances'),
    ('settle', 'sneha', '/groups/1/settle'),
    ('settlements', 'aarav', '/settlements'),
    ('budgets', 'aarav', '/budgets'),
    ('budget-form', 'aarav', '/budgets/create'),
    ('notifications', 'aarav', '/notifications'),
    ('analytics', 'priya', '/analytics'),
    ('profile', 'priya', '/profile'),
    ('admin', 'admin', '/admin'),
    ('admin-activity', 'admin', '/admin/activity'),
]

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--base', default='http://127.0.0.1:8000')
    ap.add_argument('--out', default='docs/screenshots')
    ap.add_argument('--only', default='')
    ap.add_argument('--chrome', default='/usr/bin/google-chrome')
    a = ap.parse_args()
    only = set(filter(None, a.only.split(',')))
    os.makedirs(a.out, exist_ok=True)
    with sync_playwright() as p:
        browser = p.chromium.launch(executable_path=a.chrome if os.path.exists(a.chrome) else None)
        for vp, size in [('desktop', (1366, 900)), ('mobile', (390, 844))]:
            sessions = {}
            for name, user, path in PAGES:
                if only and name not in only:
                    continue
                if user not in sessions:
                    ctx = browser.new_context(viewport={'width': size[0], 'height': size[1]},
                                              device_scale_factor=1 if vp == 'desktop' else 2)
                    pg = ctx.new_page()
                    if user:
                        pg.goto(a.base + '/login')
                        pg.fill('#login', user)
                        pg.fill('#password', 'Password@123')
                        pg.click('button[type=submit]')
                        pg.wait_for_load_state('networkidle')
                    sessions[user] = pg
                pg = sessions[user]
                errors = []
                pg.on('console', lambda m: errors.append(m.text) if m.type == 'error' else None)
                pg.goto(a.base + path)
                pg.wait_for_load_state('networkidle')
                pg.wait_for_timeout(500 if name == 'analytics' else 100)
                pg.screenshot(path=f'{a.out}/{vp}-{name}.png', full_page=True)
                if errors:
                    print(f'{vp}-{name}: console errors: {errors}')
        browser.close()
    print('saved to', a.out)

if __name__ == '__main__':
    main()
