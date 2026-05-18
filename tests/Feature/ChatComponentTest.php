<?php

use AyaAshraf\LaravelRag\Agents\DocumentSearchAgent;
use AyaAshraf\LaravelRag\Livewire\ChatComponent;
use Livewire\Livewire;

it('renders the generated answer after asking a question', function () {
    DocumentSearchAgent::fake(['This is the answer from your documents.']);

    Livewire::test(ChatComponent::class)
        ->set('query', 'What is in the document?')
        ->call('ask')
        ->assertSet('response', 'This is the answer from your documents.')
        ->assertSet('error', '')
        ->assertSee('This is the answer from your documents.');

    DocumentSearchAgent::assertPrompted('What is in the document?');
});

it('validates that a question is present', function () {
    DocumentSearchAgent::fake();

    Livewire::test(ChatComponent::class)
        ->set('query', '')
        ->call('ask')
        ->assertHasErrors(['query' => 'required']);
});
