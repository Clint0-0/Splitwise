#!/usr/bin/env python3
"""Generate docs/diagrams/er_physical.{dot,svg,png} from the LIVE splitwise_plus schema.

Reads information_schema through the mysql CLI, so the diagram always matches
the real tables, keys and foreign keys. Needs: mysql client, Graphviz (dot).

Usage:  python3 tools/generate_er.py [--mysql-args "-uroot -psecret"]
"""
import argparse
import html
import shlex
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "docs" / "diagrams"


def q(mysql_args, sql):
    cmd = ["mysql", "--default-character-set=utf8mb4", "-B", "-N", *shlex.split(mysql_args), "-e", sql]
    out = subprocess.run(cmd, check=True, capture_output=True, text=True).stdout
    return [line.split("\t") for line in out.splitlines() if line]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--mysql-args", default="-uroot")
    args = ap.parse_args()
    db = "splitwise_plus"

    cols = q(args.mysql_args, f"""
        SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA='{db}'
           AND TABLE_NAME IN (SELECT TABLE_NAME FROM information_schema.TABLES
                               WHERE TABLE_SCHEMA='{db}' AND TABLE_TYPE='BASE TABLE')
         ORDER BY TABLE_NAME, ORDINAL_POSITION""")
    fks = q(args.mysql_args, f"""
        SELECT k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE
          FROM information_schema.KEY_COLUMN_USAGE k
          JOIN information_schema.REFERENTIAL_CONSTRAINTS r
            ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
         WHERE k.TABLE_SCHEMA='{db}' AND k.REFERENCED_TABLE_NAME IS NOT NULL
         ORDER BY k.TABLE_NAME, k.COLUMN_NAME""")
    uniq = q(args.mysql_args, f"""
        SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA='{db}' AND NON_UNIQUE=0 AND INDEX_NAME <> 'PRIMARY'""")

    fk_cols = {(t, c) for t, c, *_ in fks}
    uk_cols = {(t, c) for t, c in uniq}
    tables = {}
    for t, c, typ, nullable, key, extra in cols:
        tables.setdefault(t, []).append((c, typ, nullable, key, extra))

    # colour groups make the diagram easier to read
    core = {"users", "expense_groups", "group_members", "group_invitations"}
    money = {"expenses", "expense_splits", "settlements", "budgets"}

    lines = [
        "digraph splitwise_plus {",
        '  graph [rankdir=LR, splines=spline, nodesep=0.6, ranksep=1.6, concentrate=false, fontname="Helvetica",'
        ' label="SplitWise+ — physical schema (generated from MySQL information_schema)", labelloc=t, fontsize=20];',
        '  node  [shape=plaintext, fontname="Helvetica", fontsize=10];',
        '  edge  [fontname="Helvetica", fontsize=8, color="#555555", arrowhead=crow, arrowtail=tee, dir=both];',
    ]
    for t, columns in tables.items():
        head = "#1F4E79" if t in core else "#7A3E00" if t in money else "#3B3B3B"
        rows = [f'<tr><td colspan="3" bgcolor="{head}"><font color="white"><b>{t}</b></font></td></tr>']
        for c, typ, nullable, key, extra in columns:
            marks = []
            if key == "PRI":
                marks.append("PK")
            if (t, c) in fk_cols:
                marks.append("FK")
            if (t, c) in uk_cols and key != "PRI":
                marks.append("UK")
            name = f"<b>{c}</b>" if key == "PRI" else c
            if nullable == "YES":
                name += " ?"
            gen = " (generated)" if extra.upper() in ("STORED GENERATED", "VIRTUAL GENERATED") else ""
            rows.append(
                f'<tr><td align="left" port="{c}">{name}</td>'
                f'<td align="left"><font color="#666666">{html.escape(typ)}{gen}</font></td>'
                + (f'<td align="left"><font color="#1F4E79">{",".join(marks)}</font></td></tr>'
                   if marks else '<td> </td></tr>')
            )
        lines.append(f'  {t} [label=<<table border="1" cellborder="0" cellspacing="0" cellpadding="3">'
                     + "".join(rows) + "</table>>];")
    for t, c, rt, rc, rule in fks:
        style = ', style=dashed' if t == rt else ''
        lines.append(f'  {rt}:{rc} -> {t}:{c} [xlabel="{rule.lower()}"{style}];')
    lines.append("}")

    OUT.mkdir(parents=True, exist_ok=True)
    dot = OUT / "er_physical.dot"
    dot.write_text("\n".join(lines) + "\n", encoding="utf-8")
    for fmt in ("svg", "png"):
        subprocess.run(["dot", f"-T{fmt}", "-Gdpi=110" if fmt == "png" else "-Gdpi=72",
                        str(dot), "-o", str(OUT / f"er_physical.{fmt}")], check=True)
    print(f"{len(tables)} tables, {len(fks)} foreign keys -> {OUT}/er_physical.svg|png")


if __name__ == "__main__":
    main()
