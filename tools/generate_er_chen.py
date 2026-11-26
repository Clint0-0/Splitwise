import math, subprocess, sys
def build(name, entities, rels, size_note=''):
    L=['graph G {','graph [splines=false, outputorder=edgesfirst, bgcolor="white", pad=0.3, dpi=200];',
       'node [fontname="Helvetica", fontsize=13, color="#111111", penwidth=1.4];',
       'edge [color="#111111", penwidth=1.3];']
    def P(x,y): return f'pos="{x},{y}!"'
    for e,(x,y,attrs,weak) in entities.items():
        per = 'peripheries=2,' if weak else ''
        L.append(f'"{e}" [shape=box, {per} width=1.9, height=0.65, label="{e}", fontname="Helvetica-Bold", {P(x,y)}];')
        for a,ang,kind in attrs:
            r=1.45 if len(a)<10 else 1.65
            ax=x+r*math.cos(math.radians(ang))*1.25; ay=y+r*math.sin(math.radians(ang))*0.75
            nid=f'{e}.{a}'
            lab = f'<<u>{a}</u>>' if kind=='pk' else f'"{a}"'
            style='style=dashed,' if kind=='fk' else ''
            L.append(f'"{nid}" [shape=ellipse, {style} label={lab}, fontsize=11, width=0.9, height=0.42, margin="0.04,0.02", {P(round(ax,2),round(ay,2))}];')
            L.append(f'"{e}" -- "{nid}";')
    for r,(x,y,ends,attrs) in rels.items():
        L.append(f'"{r}" [shape=diamond, label="{r.split("#")[0]}", fontsize=11, width=1.6, height=0.85, fontname="Helvetica-Bold", {P(x,y)}];')
        for ent,card,lab in ends:
            ah={'1':'teetee','N':'crowodot','1N':'crowtee','01':'teeodot'}[card]
            el = f', label="{lab}", fontsize=10, fontcolor="#334155"' if lab else ''
            L.append(f'"{r}" -- "{ent}" [dir=forward, arrowhead={ah}, arrowsize=1.1{el}];')
        for a,ax,ay in attrs:
            nid=f'{r}.{a}'
            L.append(f'"{nid}" [shape=ellipse, label="{a}", fontsize=11, width=0.9, height=0.42, {P(ax,ay)}];')
            L.append(f'"{r}" -- "{nid}";')
    L.append('}')
    open(name+'.dot','w').write('\n'.join(L))
    subprocess.run(['neato','-Tpng',name+'.dot','-o',name+'.png'],check=True)

# ---- Diagram 1: core ----
core_e = {
 'USER':       (1.5, 5.0, [('user_id',150,'pk'),('email',120,'x'),('upi_id',50,'x'),('username',180,'x'),('full_name',210,'x')], False),
 'GROUP':      (10.5,5.0, [('group_id',20,'pk'),('name',55,'x'),('is_archived',125,'x'),('created_by',335,'fk')], False),
 'EXPENSE':    (6.0, 1.2, [('expense_id',180,'pk'),('description',210,'x'),('expense_date',237,'x'),('total_amount',0,'x'),('split_method',330,'x'),('paid_by',303,'fk')], False),
 'SETTLEMENT': (6.0, 9.0, [('settlement_id',180,'pk'),('request_token',140,'x'),('amount',90,'x'),('status',35,'x')], False),
 'CATEGORY':   (6.0,-2.0, [('category_id',180,'pk'),('name',0,'x'),('parent_category_id',300,'fk')], False),
 'BUDGET':     (6.0,-5.2, [('budget_id',180,'pk'),('limit_amount',270,'x'),('period_type',0,'x'),('alert_threshold',225,'x')], False),
}
core_r = {
 'MEMBER OF': (6.0,5.0,[('USER','N',''),('GROUP','1N','')],[('role',5.0,4.0),('status',7.0,4.0)]),
 'PAYS':      (3.0,3.0,[('USER','1',''),('EXPENSE','N','')],[]),
 'RECORDS':   (9.0,3.0,[('GROUP','01',''),('EXPENSE','N','')],[]),
 'SETTLES':   (1.5,7.8,[('USER','1',''),('SETTLEMENT','N','')],[('role: payer / payee',-0.9,9.0)]),
 'HOLDS':     (10.5,7.8,[('GROUP','1',''),('SETTLEMENT','N','')],[]),
 'CLASSIFIES':(6.0,-0.45,[('CATEGORY','1',''),('EXPENSE','N','')],[]),
 'SETS':      (-1.6,1.0,[('USER','01',''),('BUDGET','N','')],[]),
 'LIMITS':    (13.6,1.0,[('GROUP','01',''),('BUDGET','N','')],[]),
}
build('er_core', core_e, core_r)

# ---- Diagram 2: expense detail ----
det_e = {
 'USER':          (1.0, 6.0, [('user_id',150,'pk'),('full_name',210,'x')], False),
 'EXPENSE_SPLIT': (6.0, 6.0, [('split_id',145,'pk'),('share_amount',112,'x'),('share_percentage',68,'x'),('share_units',32,'x')], False),
 'EXPENSE':       (11.5,6.0, [('expense_id',135,'pk'),('total_amount',35,'x'),('split_method',5,'x')], False),
 'EXPENSE_ITEM':  (6.0, 0.8, [('item_id',180,'pk'),('line_no',215,'x'),('name',250,'x'),('quantity',290,'x'),('amount',325,'x')], True),
 'EXPENSE_CHARGE':(11.5,0.8, [('charge_id',215,'pk'),('kind',250,'x'),('label',290,'x'),('amount',325,'x')], True),
 'RECEIPT':       (11.5,10.4, [('receipt_id',180,'pk'),('mime_type',0,'x'),('file_size',90,'x')], False),
 'TAG':           (16.0,2.6, [('tag_id',330,'pk'),('name',30,'x')], False),
}
det_r = {
 'OWES':          (3.5,6.0,[('USER','1',''),('EXPENSE_SPLIT','N','')],[]),
 'DIVIDED INTO':  (8.75,6.0,[('EXPENSE','1',''),('EXPENSE_SPLIT','1N','')],[]),
 'CONSUMED BY':   (6.0,3.5,[('EXPENSE_SPLIT','1N',''),('EXPENSE_ITEM','N','')],[('share_units',3.6,3.5),('share_amount',3.9,2.6)]),
 'ITEMIZED INTO': (8.75,3.4,[('EXPENSE','1',''),('EXPENSE_ITEM','N','')],[]),
 'ADDS':          (11.5,3.5,[('EXPENSE','1',''),('EXPENSE_CHARGE','N','')],[]),
 'ATTACHES':      (11.5,8.2,[('EXPENSE','1',''),('RECEIPT','N','')],[]),
 'TAGGED':        (13.9,4.2,[('EXPENSE','N',''),('TAG','N','')],[]),
}
build('er_detail', det_e, det_r)
