<?php

declare(strict_types=1);

namespace App\Livewire\Cart;

use App\Infrastructure\Api\InternalApiDispatcher;
use Livewire\Component;

class CartManager extends Component
{
    public array $cartItems = [];
    public float $subtotal = 0;
    public float $tax = 0;
    public float $total = 0;
    public array $taxBreakdown = [];
    public bool $hasItems = false;
    public array $priceChanges = [];

    protected $listeners = ['add-to-cart' => 'addItem', 'cart-refresh' => '$refresh'];

    public function mount(): void
    {
        $this->loadCart();
    }

    public function addItem(int $itemId): void
    {
        $api = app(InternalApiDispatcher::class);
        $result = $api->post('/cart/items', ['menu_item_id' => $itemId]);

        if ($result['status'] >= 400) {
            $this->dispatch('notify', type: 'error', message: $result['body']['message'] ?? 'Error adding item.');
            return;
        }

        $this->loadCart();
        $this->dispatch('cart-updated', count: count($this->cartItems));
    }

    public function updateQuantity(int $cartItemId, int $quantity): void
    {
        $api = app(InternalApiDispatcher::class);
        $result = $api->patch("/cart/items/{$cartItemId}", ['quantity' => $quantity]);

        if ($result['status'] >= 400) {
            $this->dispatch('notify', type: 'error', message: $result['body']['message'] ?? 'Error updating quantity.');
            return;
        }

        $this->loadCart();
        $this->dispatch('cart-updated', count: count($this->cartItems));
    }

    public function updateNote(int $cartItemId, string $note): void
    {
        $api = app(InternalApiDispatcher::class);
        $result = $api->patch("/cart/items/{$cartItemId}", ['note' => $note]);

        if ($result['status'] >= 400) {
            $this->dispatch('notify', type: 'error', message: $result['body']['message'] ?? 'Error updating note.');
            return;
        }

        $this->loadCart();
    }

    public function updateFlavor(int $cartItemId, string $flavor): void
    {
        $api = app(InternalApiDispatcher::class);
        $api->patch("/cart/items/{$cartItemId}", ['flavor_preference' => $flavor]);
        $this->loadCart();
    }

    public function removeItem(int $cartItemId): void
    {
        $api = app(InternalApiDispatcher::class);
        $api->delete("/cart/items/{$cartItemId}");
        $this->loadCart();
        $this->dispatch('cart-updated', count: count($this->cartItems));
    }

    public function clearCart(): void
    {
        $api = app(InternalApiDispatcher::class);
        $api->delete('/cart');
        $this->loadCart();
        $this->dispatch('cart-updated', count: 0);
    }

    private function loadCart(): void
    {
        $api = app(InternalApiDispatcher::class);
        $result = $api->get('/cart');
        $body = $result['body'];

        $this->cartItems = $body['items'] ?? [];
        $this->subtotal = (float) ($body['totals']['subtotal'] ?? 0);
        $this->tax = (float) ($body['totals']['tax'] ?? 0);
        $this->total = (float) ($body['totals']['total'] ?? 0);
        $this->taxBreakdown = $body['tax_breakdown'] ?? [];
        $this->priceChanges = $body['price_changes'] ?? [];
        $this->hasItems = !empty($this->cartItems);
    }

    public function render()
    {
        return view('livewire.cart.cart-manager');
    }
}
