@forelse($stockItems as $stock)
    <tr>
        <td>
            {{ optional($stock->item)->code }}<br>
            <span class="small text-muted">
                {{ optional($stock->item)->name }}
            </span>
        </td>

        <td>
            {{ ucfirst(str_replace('_', ' ', $stock->material_category ?? '')) }}
            <br>
            <span class="badge bg-light text-muted border small">
                {{ strtoupper($stock->status ?? '') }}
            </span>
        </td>

        <td>
            @if($stock->material_category === 'steel_plate')
                T{{ $stock->thickness_mm }} x W{{ $stock->width_mm }} x L{{ $stock->length_mm }}
            @elseif($stock->material_category === 'steel_section')
                {{ $stock->section_profile }}
            @else
                -
            @endif
        </td>

        <td>{{ $stock->grade ?? '-' }}</td>

        <td>
            @if($stock->plate_number)
                Plate: {{ $stock->plate_number }}<br>
            @endif
            @if($stock->heat_number)
                <span class="small text-muted">Heat: {{ $stock->heat_number }}</span>
            @endif
        </td>

        <td>
            {{ optional($stock->project)->code }}<br>
            <span class="small text-muted">
                {{ optional($stock->project)->name }}
            </span>
        </td>

        <td>{{ (int) $stock->qty_pcs_total }}</td>
        <td>{{ (int) $stock->qty_pcs_available }}</td>
        <td>{{ $stock->weight_kg_total ? number_format($stock->weight_kg_total, 3) : '-' }}</td>
        <td>{{ $stock->weight_kg_available ? number_format($stock->weight_kg_available, 3) : '-' }}</td>
    </tr>
@empty
    <tr>
        <td colspan="10" class="text-center text-muted py-3">
            No stock found.
        </td>
    </tr>
@endforelse