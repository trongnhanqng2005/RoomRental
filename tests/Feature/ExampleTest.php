<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_page_is_rendered_in_vietnamese(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSee('lang="vi"', false)
            ->assertSee('Tìm căn phòng phù hợp với cuộc sống hằng ngày của bạn.');
    }
}
