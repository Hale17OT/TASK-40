<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class MenuManager extends Component
{
    // Categories
    public array $categories = [];
    public string $catName = '';
    public int $catSortOrder = 0;
    public bool $catIsActive = true;
    public ?int $editingCategoryId = null;

    // Items
    public array $items = [];
    public ?int $filterCategoryId = null;
    public string $itemSku = '';
    public string $itemName = '';
    public string $itemDescription = '';
    public string $itemPrice = '';
    public string $itemTaxCategory = 'hot_prepared';
    public bool $itemIsActive = true;
    public ?int $itemCategoryId = null;
    public ?int $editingItemId = null;

    public ?string $message = null;
    public ?string $error = null;
    public string $tab = 'items';

    public function mount(): void
    {
        $this->loadCategories();
        $this->loadItems();
    }

    // ── Categories ──────────────────────────────────────────────

    public function saveCategory(): void
    {
        $this->error = null;
        $this->message = null;

        $name = trim($this->catName);
        if ($name === '') {
            $this->error = 'Category name is required.';
            return;
        }

        $data = [
            'name' => $name,
            'sort_order' => $this->catSortOrder,
            'is_active' => $this->catIsActive,
            'updated_at' => now(),
        ];

        if ($this->editingCategoryId) {
            DB::table('menu_categories')->where('id', $this->editingCategoryId)->update($data);
            $this->message = 'Category updated.';
        } else {
            $data['created_at'] = now();
            DB::table('menu_categories')->insert($data);
            $this->message = 'Category created.';
        }

        $this->resetCategoryForm();
        $this->loadCategories();
        $this->loadItems();
    }

    public function editCategory(int $id): void
    {
        $cat = DB::table('menu_categories')->find($id);
        if (!$cat) return;

        $this->editingCategoryId = $id;
        $this->catName = $cat->name;
        $this->catSortOrder = $cat->sort_order;
        $this->catIsActive = (bool) $cat->is_active;
        $this->tab = 'categories';
    }

    public function deleteCategory(int $id): void
    {
        $itemCount = DB::table('menu_items')->where('menu_category_id', $id)->count();
        if ($itemCount > 0) {
            $this->error = "Cannot delete category — it has {$itemCount} item(s). Move or delete them first.";
            return;
        }
        DB::table('menu_categories')->where('id', $id)->delete();
        $this->message = 'Category deleted.';
        $this->loadCategories();
    }

    public function resetCategoryForm(): void
    {
        $this->editingCategoryId = null;
        $this->catName = '';
        $this->catSortOrder = 0;
        $this->catIsActive = true;
    }

    // ── Items ───────────────────────────────────────────────────

    public function saveItem(): void
    {
        $this->error = null;
        $this->message = null;

        $name = trim($this->itemName);
        $sku = trim($this->itemSku);
        if ($name === '' || $sku === '') {
            $this->error = 'SKU and Name are required.';
            return;
        }
        if (!$this->itemCategoryId) {
            $this->error = 'Please select a category.';
            return;
        }
        if (!is_numeric($this->itemPrice) || (float) $this->itemPrice < 0) {
            $this->error = 'Price must be a non-negative number.';
            return;
        }

        $data = [
            'sku' => $sku,
            'menu_category_id' => $this->itemCategoryId,
            'name' => $name,
            'description' => trim($this->itemDescription) ?: null,
            'price' => (float) $this->itemPrice,
            'tax_category' => $this->itemTaxCategory,
            'is_active' => $this->itemIsActive,
            'updated_at' => now(),
        ];

        if ($this->editingItemId) {
            DB::table('menu_items')->where('id', $this->editingItemId)->update($data);
            $this->message = 'Menu item updated.';
        } else {
            // Check SKU uniqueness
            if (DB::table('menu_items')->where('sku', $sku)->exists()) {
                $this->error = 'SKU already exists.';
                return;
            }
            $data['created_at'] = now();
            DB::table('menu_items')->insert($data);
            $this->message = 'Menu item created.';
        }

        $this->resetItemForm();
        $this->loadItems();
    }

    public function editItem(int $id): void
    {
        $item = DB::table('menu_items')->find($id);
        if (!$item) return;

        $this->editingItemId = $id;
        $this->itemSku = $item->sku;
        $this->itemName = $item->name;
        $this->itemDescription = $item->description ?? '';
        $this->itemPrice = (string) $item->price;
        $this->itemTaxCategory = $item->tax_category;
        $this->itemIsActive = (bool) $item->is_active;
        $this->itemCategoryId = $item->menu_category_id;
        $this->tab = 'items';
    }

    public function deleteItem(int $id): void
    {
        DB::table('menu_items')->where('id', $id)->delete();
        $this->message = 'Menu item deleted.';
        $this->loadItems();
    }

    public function resetItemForm(): void
    {
        $this->editingItemId = null;
        $this->itemSku = '';
        $this->itemName = '';
        $this->itemDescription = '';
        $this->itemPrice = '';
        $this->itemTaxCategory = 'hot_prepared';
        $this->itemIsActive = true;
        $this->itemCategoryId = null;
    }

    public function updatedFilterCategoryId(): void
    {
        $this->loadItems();
    }

    // ── Data loading ────────────────────────────────────────────

    private function loadCategories(): void
    {
        $this->categories = DB::table('menu_categories')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($c) => (array) $c)
            ->toArray();
    }

    private function loadItems(): void
    {
        $query = DB::table('menu_items')
            ->join('menu_categories', 'menu_items.menu_category_id', '=', 'menu_categories.id')
            ->select('menu_items.*', 'menu_categories.name as category_name')
            ->orderBy('menu_items.name');

        if ($this->filterCategoryId) {
            $query->where('menu_category_id', $this->filterCategoryId);
        }

        $this->items = $query->get()->map(fn ($i) => (array) $i)->toArray();
    }

    public function render()
    {
        return view('livewire.admin.menu-manager');
    }
}
