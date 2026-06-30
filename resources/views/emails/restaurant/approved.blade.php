@extends('emails.layout')

@section('content')
    <h2 style="margin: 0 0 12px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">
        {{ __('restaurant.email.approved_heading') }}
    </h2>
    <p style="margin: 0 0 20px 0; font-size: 15px; color: #2c241c;">
        {{ __('restaurant.email.approved_body', ['business' => $profile->business_name]) }}
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 8px 0;">
        <tr>
            <td style="background-color: #21341d; border-radius: 4px;">
                <a href="{{ $loginUrl }}" style="display: inline-block; padding: 12px 24px; color: #faf5ec; font-size: 14px; font-weight: 600; text-decoration: none;">
                    {{ __('restaurant.email.approved_cta') }} &rarr;
                </a>
            </td>
        </tr>
    </table>
@endsection
