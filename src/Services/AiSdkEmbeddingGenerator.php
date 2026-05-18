<?php

namespace YourVendor\LaravelRag\Services;

use Laravel\Ai\Embeddings;

class AiSdkEmbeddingGenerator implements EmbeddingGenerator
{
    //It accepts two optional parameters: $dimensions (the length of the resulting vector) and $model (the specific AI model to use )
    public function __construct(
        private readonly ?int $dimensions = null,
        private readonly ?string $model = null,
    ) {}

    /**
     * @param  array<int, string>  $inputs
     * @return array<int, array<int, float>>
     */
    //It takes an array of strings and returns an array of floating-point arrays
    public function embed(array $inputs): array
    {
        //Before sending data to an API, the code filters out empty strings or whitespace. This prevents unnecessary API errors and saves costs
        $inputs = array_values(array_filter($inputs, static fn(string $input): bool => trim($input) !== ''));

        if ($inputs === []) {
            return [];
        }

        $provider = strtolower((string) config('services.embeddings.provider', config('ai.default_for_embeddings', 'openai')));
        $model = $this->model ?: config("services.$provider.embedding_model");
        $dimensions = $this->dimensions ?? config("services.$provider.embedding_dimensions");

        // if ($provider === 'gemini' && is_string($model) && str_starts_with($model, 'models/')) {
        //     $model = substr($model, 7);
        // }

        $request = Embeddings::for($inputs);

        if (is_int($dimensions)) {
            $request->dimensions($dimensions);
        }

        $response = $request->generate($provider, $model);
        //It returns a high-dimensional vector (a list of numbers) that represents the semantic meaning of your text
        return array_map(function (array $embedding): array {
            return array_map(static fn(int|float $value): float => (float) $value, $embedding);
        }, $response->embeddings);
    }

    public function model(): string
    {
        $provider = strtolower((string) config('services.embeddings.provider', 'openai'));

        return $this->model ?: (string) config("services.$provider.embedding_model", $provider);
    }
}   

