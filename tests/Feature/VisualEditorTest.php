<?php

namespace Tsrgtm\FilamentMediaLibrary\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use Filament\Facades\Filament;
use Filament\Panel;
use Tsrgtm\FilamentMediaLibrary\Filament\MediaLibraryPlugin;
use Tsrgtm\FilamentMediaLibrary\Filament\Resources\MediaResource\Pages\ListMedia;
use Tsrgtm\FilamentMediaLibrary\Models\Media;
use Tsrgtm\FilamentMediaLibrary\Tests\TestCase;
use Tsrgtm\FilamentMediaLibrary\Tests\TestModel;

class VisualEditorTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Configure session driver and push middleware globally
        $app['config']->set('session.driver', 'array');
        
        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->pushMiddleware(\Illuminate\Session\Middleware\StartSession::class);
        $kernel->pushMiddleware(\Illuminate\View\Middleware\ShareErrorsFromSession::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // Set the current panel to the admin panel registered by TestPanelServiceProvider
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Share errors bag to avoid Livewire view rendering issues in testbench
        $errors = new ViewErrorBag();
        $errors->put('default', new MessageBag());
        View::share('errors', $errors);

        // Start session manually and share with request
        $session = $this->app['session']->driver('array');
        $session->start();
        $session->put('errors', $errors);
        $this->app['request']->setLaravelSession($session);
    }

    /** @test */
    public function it_correctly_identifies_svg_media()
    {
        $dummy = new Media();
        $dummy->id = 1;
        $file = UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml');
        $media = $dummy->addMedia($file)->toCollection('logos');

        $this->assertTrue($media->isSvg());
        $this->assertTrue($media->isText());
    }

    /** @test */
    public function it_can_save_svg_content_via_editor()
    {
        $dummy = new Media();
        $dummy->id = 1;
        $file = UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml');
        $media = $dummy->addMedia($file)->toCollection('logos');

        $svgContent = '<svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" /></svg>';

        Livewire::test(ListMedia::class)
            ->call('saveSvgContent', $media->id, $svgContent);

        $media->refresh();
        $this->assertEquals($svgContent, Storage::disk('public')->get($media->path));
        $this->assertEquals(strlen($svgContent), $media->size);
    }

    /** @test */
    public function it_can_save_text_content_via_editor()
    {
        $dummy = new Media();
        $dummy->id = 1;
        $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');
        $media = $dummy->addMedia($file)->toCollection('notes');

        $textContent = 'Hello visual editor world!';

        Livewire::test(ListMedia::class)
            ->call('saveVisualTextContent', $media->id, $textContent);

        $media->refresh();
        $this->assertEquals($textContent, Storage::disk('public')->get($media->path));
        $this->assertEquals(strlen($textContent), $media->size);
    }

    /** @test */
    public function it_can_update_pdf_metadata_via_editor()
    {
        $dummy = new Media();
        $dummy->id = 1;
        $file = UploadedFile::fake()->create('document.pdf', 10, 'application/pdf');
        $media = $dummy->addMedia($file)->toCollection('documents');

        Livewire::test(ListMedia::class)
            ->call('saveVisualPdfMeta', $media->id, [
                'title' => 'My Premium PDF',
                'author' => 'AI Creator',
                'subject' => 'Visual Testing',
                'keywords' => 'test, pdf, library',
                'creator' => 'Laravel Filament',
            ]);

        $media->refresh();
        $this->assertEquals('My Premium PDF', $media->custom_properties['pdf_title']);
        $this->assertEquals('AI Creator', $media->custom_properties['pdf_author']);
    }

    /** @test */
    public function it_can_update_metadata_via_editor()
    {
        $dummy = new Media();
        $dummy->id = 1;
        $file = UploadedFile::fake()->image('avatar.jpg');
        $media = $dummy->addMedia($file)->toCollection('avatars');

        Livewire::test(ListMedia::class)
            ->call('saveVisualMetadata', $media->id, 'Updated Title', 'Cool Avatar alt text');

        $media->refresh();
        $this->assertEquals('Updated Title', $media->name);
        $this->assertEquals('Cool Avatar alt text', $media->alt_text);
    }
}
