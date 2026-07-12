<x-email-layout
    title="New Agency Registration"
    heading="New Agency Registration"
    subtitle="A workspace is awaiting review."
>
    <p>The following agency registration has been submitted and is currently pending approval.</p>

    <x-slot:details>
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px;padding:20px;">
            <tr><td style="color:#6b7280;font-size:14px;">Agency Name</td><td align="right" style="color:#111827;font-weight:bold;">{{ $agencyName }}</td></tr>
            <tr><td style="padding-top:12px;color:#6b7280;font-size:14px;">Owner</td><td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ $ownerName }}</td></tr>
            <tr><td style="padding-top:12px;color:#6b7280;font-size:14px;">Owner Email</td><td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ $ownerEmail }}</td></tr>
            <tr><td style="padding-top:12px;color:#6b7280;font-size:14px;">Business Email</td><td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ $businessEmail }}</td></tr>
            <tr><td style="padding-top:12px;color:#6b7280;font-size:14px;">Business Phone</td><td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ $businessPhone }}</td></tr>
            <tr><td style="padding-top:12px;color:#6b7280;font-size:14px;">Location</td><td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ $city }}, {{ $country }}</td></tr>
            @if(!empty($address))
                <tr><td style="padding-top:12px;color:#6b7280;font-size:14px;">Address</td><td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ $address }}</td></tr>
            @endif
            @if(!empty($description))
                <tr><td style="padding-top:12px;color:#6b7280;font-size:14px;">Description</td><td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ $description }}</td></tr>
            @endif
            <tr><td style="padding-top:12px;color:#6b7280;font-size:14px;">Tenant Key</td><td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ $tenantKey }}</td></tr>
            <tr><td style="padding-top:12px;color:#6b7280;font-size:14px;">Status</td><td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ ucfirst($status) }}</td></tr>
        </table>
    </x-slot:details>

    <p>Please review this workspace request and take the appropriate action in the admin panel.</p>
</x-email-layout>
