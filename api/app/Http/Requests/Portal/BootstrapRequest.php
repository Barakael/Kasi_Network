<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The handshake the captive portal opens with.
 *
 * Every field except the site identifier comes from the MikroTik login page's
 * template variables, forwarded by router-config/login.html. They are optional
 * because a client may reach the portal directly -- for instance by scanning the
 * QR code on a printed voucher -- in which case there is no hotspot handshake to
 * carry along.
 */
class BootstrapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             * The site's nas_identifier, baked into login.html when the router is
             * provisioned. MikroTik exposes no template variable for
             * radius-location-id, so it is written into the page instead of being
             * read at runtime.
             */
            'site' => ['required', 'string', 'max:64'],

            'mac' => ['nullable', 'string', 'max:32'],
            'ip' => ['nullable', 'ip'],

            // $(link-login-only): where the CHAP response is posted to log in.
            'link_login' => ['nullable', 'string', 'max:512'],

            // Where the client was heading before being intercepted.
            'link_orig' => ['nullable', 'string', 'max:512'],

            // $(server-name): which hotspot server on the router served the page.
            'server_name' => ['nullable', 'string', 'max:64'],
        ];
    }
}
