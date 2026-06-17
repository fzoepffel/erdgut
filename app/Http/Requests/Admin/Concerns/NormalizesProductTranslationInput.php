<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Support\HumanText;

trait NormalizesProductTranslationInput
{
    protected function normalizeProductTranslationInput(): void
    {
        $translations = $this->input('translations');
        if (is_array($translations)) {
            $fields = ['name', 'origin', 'variety', 'short_description', 'taste_profile'];

            foreach ($translations as $locale => $row) {
                if (! is_array($row)) {
                    continue;
                }

                foreach ($fields as $field) {
                    if (isset($row[$field]) && is_string($row[$field])) {
                        $translations[$locale][$field] = HumanText::normalize($row[$field]);
                    }
                }
            }
        }

        $merge = [];
        if (is_array($translations)) {
            $merge['translations'] = $translations;
        }

        $customTags = $this->input('custom_taste_tags');
        if (is_array($customTags)) {
            foreach ($customTags as $index => $tag) {
                if (! is_array($tag)) {
                    continue;
                }

                foreach (['de', 'en', 'fr'] as $locale) {
                    if (isset($tag[$locale]) && is_string($tag[$locale])) {
                        $customTags[$index][$locale] = HumanText::normalize($tag[$locale]);
                    }
                }
            }

            $merge['custom_taste_tags'] = $customTags;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
