{{-- Price summary not computed server-side --}}
@php
    $enabledDiscounts = old('discounts') ?? $reservationDiscounts;
@endphp
<div class="form-group" id="donation-form-group">
    <h3 class="form-group-title">{{ __('Summary') }}{{ $useFreePrice ? " (" . __('suggested rate for free pricing') . ")" : "" }}</h3>
    {{-- Mention « tarif membre » : affichée par le JS dès que la remise membre s'applique. --}}
    <p id="pep-member-note" class="hidden" style="color:#059669;font-weight:600;margin:0 0 .5rem">
        ✓ {{ __('Pépite member rate applied: :pct% off, already deducted below.', ['pct' => (int) app(\App\Models\SystemSettings::class)->member_discount_percent]) }}
    </p>
    <div class="form-element">
        <div class="form-field">
            <p id="total-cost-p" class="cost">
                <span class="cost-label">{{ __('Initial total') }}:</span>
                <span class="cost-value"><span id="total-cost">{{currency(0,$owner)}}</span></span>
            </p>
            @foreach($discounts as $discount)
                <p id="discount_{{ $discount->id }}-cost-p" class="cost {{ in_array($discount->id, $enabledDiscounts) ? '' : 'hidden' }}">
                    <span class="cost-label">{{ $discount->description }}:</span>
                    <span class="cost-value"><span id="discount_{{ $discount->id }}-cost">{{ currency($discount->value,$owner) }}</span></span>
                </p>
            @endforeach
            {{-- Heure offerte membre (La Pépite), affichée dynamiquement par le JS --}}
            <p id="pep-free-cost-p" class="cost hidden">
                <span class="cost-label">{{ __('Free hour (member)') }}:</span>
                <span class="cost-value"><span id="pep-free-cost">{{ currency(0,$owner) }}</span></span>
            </p>
            {{-- Remise membre La Pépite (-10 %), affichée dynamiquement par le JS --}}
            <p id="pep-member-cost-p" class="cost hidden">
                <span class="cost-label">{{ __('Member discount') }} (−{{ (int) app(\App\Models\SystemSettings::class)->member_discount_percent }}%):</span>
                <span class="cost-value"><span id="pep-member-cost">{{ currency(0,$owner) }}</span></span>
            </p>
            <p id="special_discount-cost-p" class="cost {{ $specialDiscount > 0 ? '' : 'hidden' }}">
                <span class="cost-label">{{ __('Special discount (admin)') }}:</span>
                <span class="cost-value"><span id="special_discount-cost">{{ currency($specialDiscount,$owner) }}</span></span>
            </p>
            <p id="donation-cost-p" class="cost {{ $donation > 0 ? '' : 'hidden'}}">
                <span class="cost-label">{{ __('Additional donation') }}:</span>
                <span class="cost-value"><span id="donation-cost">{{ currency($donation,$owner) }}</span></span>
            </p>
            <p id="final-cost-p" class="cost">
                <span class="cost-label">{{ __('Total') }}:</span>
                <span class="cost-value"><span id="final-cost">{{currency(0,$owner)}}</span></span>
            </p>
        </div>

    </div>
</div>
