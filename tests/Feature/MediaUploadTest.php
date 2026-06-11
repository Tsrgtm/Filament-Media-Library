<?php

namespace Tsrgtm\FilamentMediaLibrary\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tsrgtm\FilamentMediaLibrary\Models\Media;
use Tsrgtm\FilamentMediaLibrary\Tests\TestCase;
use Tsrgtm\FilamentMediaLibrary\Tests\TestModel;

class MediaUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** @test */
    public function it_can_upload_media_using_fluent_api()
    {
        $model = TestModel::create(['name' => 'John Doe']);
        $file = UploadedFile::fake()->image('avatar.jpg', 600, 600);

        $media = $model->addMedia($file)
            ->withAltText('Profile Photo')
            ->toCollection('avatar');

        $this->assertInstanceOf(Media::class, $media);
        $this->assertEquals('avatar', $media->collection_name);
        $this->assertEquals('Profile Photo', $media->alt_text);
        
        // Assert file exists on fake disk
        Storage::disk('public')->assertExists($media->getPath());
    }

    /** @test */
    public function it_enforces_single_mode_by_deleting_previous_media()
    {
        $model = TestModel::create(['name' => 'Single Test']);
        
        $file1 = UploadedFile::fake()->image('photo1.jpg');
        $media1 = $model->addMedia($file1)->toCollection('avatar');

        $file2 = UploadedFile::fake()->image('photo2.jpg');
        $media2 = $model->addMedia($file2)->toCollection('avatar');

        // Assert first is soft-deleted
        $this->assertSoftDeleted('media', ['id' => $media1->id]);
        // Assert second is active
        $this->assertDatabaseHas('media', [
            'id' => $media2->id,
            'collection_name' => 'avatar',
            'deleted_at' => null
        ]);
    }

    /** @test */
    public function it_can_detect_duplicate_uploads_and_reuse_file()
    {
        $model1 = TestModel::create(['name' => 'Model One']);
        $model2 = TestModel::create(['name' => 'Model Two']);

        $fileData = UploadedFile::fake()->image('duplicate.jpg');
        
        $media1 = $model1->addMedia($fileData)->toCollection('gallery');
        
        // Upload identical file for second model
        $media2 = $model2->addMedia($fileData)->toCollection('gallery');

        $this->assertEquals($media1->hash, $media2->hash);
        // Link strategy means they share the same physical path
        $this->assertEquals($media1->getPath(), $media2->getPath());
    }

    /** @test */
    public function it_validates_allowed_mimes()
    {
        $model = TestModel::create(['name' => 'Validation Test']);
        $file = UploadedFile::fake()->create('script.sh', 100, 'text/x-shellscript');

        $this->expectException(\Exception::class);
        $model->addMedia($file)->toCollection('gallery');
    }

    /** @test */
    public function it_is_compatible_with_livewire_temporary_uploaded_file()
    {
        $model = TestModel::create(['name' => 'Livewire Upload Test']);
        
        // Use standard Livewire TemporaryUploadedFile fake upload
        $file = \Livewire\Features\SupportFileUploads\TemporaryUploadedFile::fake()->image('livewire_upload.jpg');

        $media = $model->addMedia($file)->toCollection('gallery');

        $this->assertInstanceOf(Media::class, $media);
        $this->assertEquals('livewire-upload.jpg', $media->file_name);
        Storage::disk('public')->assertExists($media->getPath());
    }

    /** @test */
    public function it_can_add_media_directly_using_standalone_media_model()
    {
        $dummy = new Media();
        $dummy->id = 1;
        $file = UploadedFile::fake()->image('standalone.jpg');
        $media = $dummy->addMedia($file)->toCollection('standalone');

        $this->assertInstanceOf(Media::class, $media);
        $this->assertEquals('standalone', $media->collection_name);
        Storage::disk('public')->assertExists($media->getPath());
    }

    /** @test */
    public function it_can_generate_responsive_images_at_custom_widths()
    {
        $dummy = new Media();
        $dummy->id = 1;
        // Create an image large enough so widths like 480 and 800 are smaller than the original width
        $file = UploadedFile::fake()->image('big-image.jpg', 1600, 1600);
        
        $media = $dummy->addMedia($file)
            ->withResponsiveWidths([480, 800])
            ->toCollection('responsive_test');

        $this->assertInstanceOf(Media::class, $media);
        $media->refresh();
        $this->assertNotNull($media->responsive_images);
        $this->assertArrayHasKey(480, $media->responsive_images);
        $this->assertArrayHasKey(800, $media->responsive_images);
        $this->assertArrayNotHasKey(1200, $media->responsive_images); // since 1200 was not in requested widths

        // Assert files exist on fake disk
        Storage::disk('public')->assertExists($media->getDirectory() . "/responsive/" . $media->responsive_images[480]);
        Storage::disk('public')->assertExists($media->getDirectory() . "/responsive/" . $media->responsive_images[800]);
    }
}
