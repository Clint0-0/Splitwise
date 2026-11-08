<?php
declare(strict_types=1);

namespace App\Models;

final class CategoryModel extends Model
{
    /** Active categories, parents first, children indented under them. */
    public function allActive(): array
    {
        return $this->all('SELECT c.category_id, c.name, c.parent_category_id, c.icon, c.color, p.name AS parent_name
                             FROM categories c
                             LEFT JOIN categories p ON p.category_id = c.parent_category_id
                            WHERE c.is_active = 1
                            ORDER BY COALESCE(p.name, c.name), c.parent_category_id IS NOT NULL, c.name');
    }

    public function isActive(int $id): bool
    {
        return (bool) $this->value('SELECT 1 FROM categories WHERE category_id = ? AND is_active = 1', [$id]);
    }
}
