<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PromotionManager extends Component
{
    public array $promotions = [];

    public string $name = '';
    public string $type = 'percentage_off';
    public string $rulesJson = '{}';
    public string $exclusionGroup = '';
    public string $startsAt = '';
    public string $endsAt = '';
    public bool $isActive = true;
    public ?int $editingId = null;

    public ?string $message = null;
    public ?string $error = null;

    public array $availableTypes = [
        'percentage_off' => 'Percentage Off',
        'flat_discount' => 'Flat Discount',
        'bogo' => 'Buy One Get One',
        'percentage_off_second' => 'Percentage Off Second Item',
    ];

    public function mount(): void
    {
        $this->startsAt = now()->format('Y-m-d\TH:i');
        $this->endsAt = now()->addDays(7)->format('Y-m-d\TH:i');
        $this->loadPromotions();
    }

    public function save(): void
    {
        $this->error = null;
        $this->message = null;

        $name = trim($this->name);
        if ($name === '') {
            $this->error = 'Promotion name is required.';
            return;
        }

        if (!array_key_exists($this->type, $this->availableTypes)) {
            $this->error = 'Invalid promotion type.';
            return;
        }

        // Validate rules JSON
        $rules = json_decode($this->rulesJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error = 'Rules must be valid JSON. Example: {"threshold": 30, "percentage": 10}';
            return;
        }

        if (empty($this->startsAt) || empty($this->endsAt)) {
            $this->error = 'Start and end dates are required.';
            return;
        }

        if ($this->startsAt >= $this->endsAt) {
            $this->error = 'End date must be after start date.';
            return;
        }

        $data = [
            'name' => $name,
            'type' => $this->type,
            'rules' => json_encode($rules),
            'exclusion_group' => trim($this->exclusionGroup) ?: null,
            'starts_at' => str_replace('T', ' ', $this->startsAt) . ':00',
            'ends_at' => str_replace('T', ' ', $this->endsAt) . ':00',
            'is_active' => $this->isActive,
            'updated_at' => now(),
        ];

        if ($this->editingId) {
            DB::table('promotions')->where('id', $this->editingId)->update($data);
            $this->message = 'Promotion updated.';
        } else {
            $data['created_at'] = now();
            DB::table('promotions')->insert($data);
            $this->message = 'Promotion created.';
        }

        $this->resetForm();
        $this->loadPromotions();
    }

    public function edit(int $id): void
    {
        $promo = DB::table('promotions')->find($id);
        if (!$promo) return;

        $this->editingId = $id;
        $this->name = $promo->name;
        $this->type = $promo->type;
        $this->rulesJson = is_string($promo->rules) ? $promo->rules : json_encode($promo->rules);
        $this->exclusionGroup = $promo->exclusion_group ?? '';
        $this->startsAt = date('Y-m-d\TH:i', strtotime($promo->starts_at));
        $this->endsAt = date('Y-m-d\TH:i', strtotime($promo->ends_at));
        $this->isActive = (bool) $promo->is_active;
    }

    public function delete(int $id): void
    {
        // Check if promotion has been applied to orders
        $applied = DB::table('applied_promotions')->where('promotion_id', $id)->exists();
        if ($applied) {
            DB::table('promotions')->where('id', $id)->update(['is_active' => false, 'updated_at' => now()]);
            $this->message = 'Promotion has been applied to orders and cannot be deleted. It has been deactivated instead.';
        } else {
            DB::table('promotions')->where('id', $id)->delete();
            $this->message = 'Promotion deleted.';
        }
        $this->loadPromotions();
    }

    public function toggleActive(int $id): void
    {
        $promo = DB::table('promotions')->find($id);
        if (!$promo) return;

        DB::table('promotions')->where('id', $id)->update([
            'is_active' => !$promo->is_active,
            'updated_at' => now(),
        ]);

        $this->message = $promo->is_active ? 'Promotion deactivated.' : 'Promotion activated.';
        $this->loadPromotions();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->type = 'percentage_off';
        $this->rulesJson = '{}';
        $this->exclusionGroup = '';
        $this->startsAt = now()->format('Y-m-d\TH:i');
        $this->endsAt = now()->addDays(7)->format('Y-m-d\TH:i');
        $this->isActive = true;
    }

    private function loadPromotions(): void
    {
        $this->promotions = DB::table('promotions')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($p) {
                $row = (array) $p;
                $row['rules_decoded'] = is_string($p->rules) ? json_decode($p->rules, true) : $p->rules;
                return $row;
            })
            ->toArray();
    }

    public function render()
    {
        return view('livewire.admin.promotion-manager');
    }
}
