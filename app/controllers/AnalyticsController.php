<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Models\AnalyticsModel;

/** Analytics page + JSON endpoints consumed by Chart.js (real data only). */
final class AnalyticsController extends Controller
{
    public function index(): void
    {
        $uid = Auth::requireId();
        $m = new AnalyticsModel();
        $this->view('analytics/index', [
            'title'   => 'Analytics',
            'totals'  => $m->totals($uid),
            'top'     => $m->topExpenses($uid),
            'scripts' => ['vendor/chartjs/chart.umd.min.js', 'js/analytics.js'],
        ]);
    }

    private function data(string $type): void
    {
        $uid = Auth::requireId();
        $m = new AnalyticsModel();
        $from = $this->date('from', date('Y-m-01', strtotime('-11 months')));
        $to   = $this->date('to', date('Y-m-d'));
        $payload = match ($type) {
            'categories'   => $m->categoryShare($uid, $from, $to),
            'monthly'      => $this->fillMonths($m->monthly($uid, substr($from, 0, 7)), substr($from, 0, 7), substr($to, 0, 7)),
            'groups'       => $m->groups($uid),
            'paid-vs-owed' => $m->paidVsOwed($uid),
            'settlements'  => $m->settlements($uid),
            default        => throw new HttpException(404),
        };
        $this->json(['type' => $type, 'from' => $from, 'to' => $to, 'data' => $payload]);
    }

    public function categories(): void { $this->data('categories'); }
    public function monthly(): void { $this->data('monthly'); }
    public function groups(): void { $this->data('groups'); }
    public function paidVsOwed(): void { $this->data('paid-vs-owed'); }
    public function settlements(): void { $this->data('settlements'); }

    /** Include months with no spending as 0 so the trend line is continuous. */
    private function fillMonths(array $rows, string $from, string $to): array
    {
        $by = array_column($rows, null, 'month');
        $out = [];
        for ($m = new \DateTime($from . '-01'); $m->format('Y-m') <= $to; $m->modify('+1 month')) {
            $k = $m->format('Y-m');
            $out[] = ['month' => $k, 'total' => $by[$k]['total'] ?? '0.00', 'expense_count' => (int) ($by[$k]['expense_count'] ?? 0)];
        }
        return $out;
    }

    private function date(string $key, string $default): string
    {
        $v = $this->request->query($key);
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : $default;
    }
}
