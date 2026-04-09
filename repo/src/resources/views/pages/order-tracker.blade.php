<x-layouts.kiosk title="Order Status">
    <div class="mx-auto max-w-2xl p-6">
        <h2 class="mb-6 text-2xl font-bold text-gray-900">Order Status</h2>
        <livewire:order.order-tracker :trackingToken="$trackingToken" />
    </div>
</x-layouts.kiosk>
