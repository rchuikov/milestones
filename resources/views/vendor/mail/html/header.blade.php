@props(['url'])

<tr>
    <td class="header">
        <a href="{{ $url }}" style="text-decoration: none;">
            <img
                src="{{ asset('images/logo.png') }}"
                alt="MyMilestones"
                style="width: 48px; height: 48px; vertical-align: middle; border: 0;"
            >
            <span style="font-size: 22px; font-weight: bold; color: #1d4ed8; vertical-align: middle;">
                MyMilestones
            </span>
        </a>
    </td>
</tr>
