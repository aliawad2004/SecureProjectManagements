<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AttachmentAccessorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_formatted_size_accessor_returns_correct_format(): void
    {
        $attachment1 = Attachment::factory()->make(['file_size' => 500]); // 500 Bytes
        $attachment2 = Attachment::factory()->make(['file_size' => 1024]); // 1 KB
        $attachment3 = Attachment::factory()->make(['file_size' => 1536]); // 1.5 KB
        $attachment4 = Attachment::factory()->make(['file_size' => 1024 * 1024]); // 1 MB
        $attachment5 = Attachment::factory()->make(['file_size' => 2 * 1024 * 1024 * 1024]); // 2 GB

        $this->assertEquals('500.00 B', $attachment1->formatted_size);
        $this->assertEquals('1.00 KB', $attachment2->formatted_size);
        $this->assertEquals('1.50 KB', $attachment3->formatted_size);
        $this->assertEquals('1.00 MB', $attachment4->formatted_size);
        $this->assertEquals('2.00 GB', $attachment5->formatted_size);
    }
}
