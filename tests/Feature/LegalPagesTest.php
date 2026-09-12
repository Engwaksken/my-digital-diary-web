<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_policy_renders_allowed_html_and_removes_unsafe_markup(): void
    {
        SiteSetting::current()->forceFill([
            'site_name' => 'Diary Demo',
            'logo_path' => 'branding/system-logo.png',
            'privacy_policy_content' => '<h2 onclick="alert(1)">Your privacy</h2><strong data-test="x">Important</strong><ul class="list"><li>Control your data</li></ul><script>alert("xss")</script><a href="https://example.test">No links</a>',
        ])->save();

        $response = $this->get(route('privacy-policy'));

        $response->assertOk()
            ->assertSee('<h2>Your privacy</h2>', false)
            ->assertSee('<strong>Important</strong>', false)
            ->assertSee('<ul><li>Control your data</li></ul>', false)
            ->assertSee('Diary Demo logo')
            ->assertSee('pm-legal-header-logo')
            ->assertSee('pm-legal-watermark')
            ->assertDontSee('<h2 onclick', false)
            ->assertDontSee('<strong data-test', false)
            ->assertDontSee('alert("xss")', false)
            ->assertDontSee('<a href="https://example.test"', false);
    }

    public function test_terms_page_keeps_plain_text_readable_and_hides_branding_when_no_logo_is_configured(): void
    {
        SiteSetting::current()->forceFill([
            'logo_path' => null,
            'terms_of_use_content' => "First line\nSecond line",
        ])->save();

        $response = $this->get(route('terms-of-use'));

        $response->assertOk()
            ->assertSee('First line<br>', false)
            ->assertSee('Second line')
            ->assertDontSee('pm-legal-header-logo')
            ->assertDontSee('pm-legal-watermark');
    }

    public function test_admin_save_strips_unsupported_tags_and_attributes_from_legal_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.settings.privacy.update'), [
            'privacy_policy_version' => '2.0',
            'privacy_policy_content' => '<h3 id="heading">Policy details</h3><ol style="color:red"><li>One <em onclick="bad()">item</em></li></ol><img src="bad"><iframe src="bad">hidden</iframe>',
        ]);

        $response->assertRedirect(route('admin.settings.edit', ['tab' => 'privacy']));

        $content = (string) SiteSetting::current()->privacy_policy_content;

        $this->assertStringContainsString('<h3>Policy details</h3>', $content);
        $this->assertStringContainsString('<ol><li>One <em>item</em></li></ol>', $content);
        $this->assertStringNotContainsString('id=', $content);
        $this->assertStringNotContainsString('style=', $content);
        $this->assertStringNotContainsString('onclick', $content);
        $this->assertStringNotContainsString('<img', $content);
        $this->assertStringNotContainsString('<iframe', $content);
        $this->assertStringNotContainsString('hidden', $content);
    }
}
