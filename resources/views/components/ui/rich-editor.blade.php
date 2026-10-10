@props([
    'name',
    'label',
    'value' => null,
    'placeholder' => 'Nhập nội dung...',
    'hint' => null,
    'required' => false,
    'minWords' => 0,
    'maxWords' => null,
    'heightClass' => 'min-h-[14rem]',
    'singleLine' => false,
])

@php
    $id = $attributes->get('id', $name);
    $hasError = $errors->has($name);
    $initialValue = old($name, $value) ?? '';
@endphp

<div
    class="rich-editor-wrapper space-y-2"
    data-rich-editor
    data-editor-id="{{ $id }}"
    @if ($minWords > 0) data-min-words="{{ $minWords }}" @endif
    @if ($maxWords) data-max-words="{{ $maxWords }}" @endif
    @if ($singleLine) data-single-line="true" @endif
>
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <label class="block text-sm font-bold text-slate-800" for="{{ $id }}-editor">
            {{ $label }}
            @if (! $required)
                <span class="font-medium text-slate-400">({{ __('ui.forms.optional') }})</span>
            @else
                <span class="text-danger-600">*</span>
            @endif
        </label>

        <div
            class="text-xs font-semibold text-slate-500 transition-colors"
            data-counter-badge
            aria-live="polite"
        >
            <span data-word-count>0</span> từ (<span data-char-count>0</span> ký tự)
        </div>
    </div>

    <div class="overflow-hidden rounded-control border @if ($hasError) border-danger-400 ring-1 ring-danger-200 @else border-slate-300 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100 @endif bg-white shadow-sm transition">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50/80 px-2.5 py-1.5 text-xs text-slate-700 select-none">
            @unless ($singleLine)
                <!-- Paragraph / Heading -->
                <select
                    class="h-8 rounded border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:border-slate-300 focus:border-brand-500 focus:outline-none"
                    data-toolbar-cmd="formatBlock"
                    title="Định dạng đoạn văn"
                >
                    <option value="p">Đoạn văn (Paragraph)</option>
                    <option value="h1">Tiêu đề 1 (H1)</option>
                    <option value="h2">Tiêu đề 2 (H2)</option>
                    <option value="h3">Tiêu đề 3 (H3)</option>
                    <option value="h4">Tiêu đề 4 (H4)</option>
                    <option value="blockquote">Trích dẫn</option>
                </select>
            @endunless

            <!-- Font Family -->
            <select
                class="h-8 rounded border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:border-slate-300 focus:border-brand-500 focus:outline-none"
                data-toolbar-cmd="fontName"
                title="Phông chữ"
            >
                <option value="Plus Jakarta Sans, sans-serif">Mặc định (Inter / Jakarta)</option>
                <option value="Arial, sans-serif">Arial</option>
                <option value="'Times New Roman', serif">Times New Roman</option>
                <option value="Roboto, sans-serif">Roboto</option>
                <option value="'Courier New', monospace">Courier New</option>
                <option value="Georgia, serif">Georgia</option>
            </select>

            <!-- Font Size -->
            <select
                class="h-8 rounded border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:border-slate-300 focus:border-brand-500 focus:outline-none"
                data-toolbar-cmd="fontSize"
                title="Cỡ chữ"
            >
                <option value="2">13px (Chuẩn bản thảo)</option>
                <option value="3" selected>14px (Mặc định)</option>
                <option value="4">16px (Vừa)</option>
                <option value="5">18px (Lớn)</option>
                <option value="6">24px (Tiêu đề)</option>
            </select>

            <!-- Text Color -->
            <label class="relative inline-flex h-8 cursor-pointer items-center justify-center rounded border border-slate-200 bg-white px-2 text-xs font-bold text-slate-700 hover:bg-slate-100" title="Màu chữ">
                <span class="flex items-center gap-0.5">
                    <span class="text-xs font-bold text-slate-800">A</span>
                    <span class="inline-block h-1 w-3 rounded-full bg-brand-600" data-color-indicator></span>
                </span>
                <input class="absolute inset-0 size-full cursor-pointer opacity-0" type="color" value="#0f172a" data-toolbar-color="foreColor">
            </label>

            <!-- Highlight / Background Color -->
            <label class="relative inline-flex h-8 cursor-pointer items-center justify-center rounded border border-slate-200 bg-white px-2 text-xs font-bold text-slate-700 hover:bg-slate-100" title="Màu nền nổi bật">
                <span class="flex items-center gap-0.5">
                    <span class="text-xs font-bold bg-warning-200 px-0.5 rounded">A</span>
                </span>
                <input class="absolute inset-0 size-full cursor-pointer opacity-0" type="color" value="#fef08a" data-toolbar-color="hiliteColor">
            </label>

            <span class="mx-0.5 h-4 w-px bg-slate-300"></span>

            <!-- Inline Styles -->
            <button class="grid size-8 place-items-center rounded border border-transparent font-bold text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-btn="bold" title="In đậm (Ctrl+B)">
                <b>B</b>
            </button>
            <button class="grid size-8 place-items-center rounded border border-transparent font-serif italic text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-btn="italic" title="In nghiêng (Ctrl+I)">
                <i>I</i>
            </button>
            <button class="grid size-8 place-items-center rounded border border-transparent underline text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-btn="underline" title="Gạch chân (Ctrl+U)">
                <u>U</u>
            </button>
            <button class="grid size-8 place-items-center rounded border border-transparent line-through text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-btn="strikeThrough" title="Gạch ngang">
                <s>S</s>
            </button>
            <button class="grid size-8 place-items-center rounded border border-transparent text-xs text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-btn="subscript" title="Chỉ số dưới">
                x<sub>2</sub>
            </button>
            <button class="grid size-8 place-items-center rounded border border-transparent text-xs text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-btn="superscript" title="Chỉ số trên">
                x<sup>2</sup>
            </button>

            <span class="mx-0.5 h-4 w-px bg-slate-300"></span>

            <!-- Alignments -->
            <button class="grid size-8 place-items-center rounded border border-transparent text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-btn="justifyLeft" title="Căn trái">
                <i class="size-3.5" data-lucide="align-left"></i>
            </button>
            <button class="grid size-8 place-items-center rounded border border-transparent text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-btn="justifyCenter" title="Căn giữa">
                <i class="size-3.5" data-lucide="align-center"></i>
            </button>
            <button class="grid size-8 place-items-center rounded border border-transparent text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-btn="justifyRight" title="Căn phải">
                <i class="size-3.5" data-lucide="align-right"></i>
            </button>
            <button class="grid size-8 place-items-center rounded border border-transparent text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-btn="justifyFull" title="Căn đều">
                <i class="size-3.5" data-lucide="align-justify"></i>
            </button>

            @unless ($singleLine)
                <span class="mx-0.5 h-4 w-px bg-slate-300"></span>

                <!-- Lists & Indent -->
                <button class="grid size-8 place-items-center rounded border border-transparent text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-btn="insertUnorderedList" title="Danh sách dấu chấm">
                    <i class="size-3.5" data-lucide="list"></i>
                </button>
                <button class="grid size-8 place-items-center rounded border border-transparent text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-btn="insertOrderedList" title="Danh sách số">
                    <i class="size-3.5" data-lucide="list-ordered"></i>
                </button>
                <button class="grid size-8 place-items-center rounded border border-transparent text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-btn="outdent" title="Giảm lề">
                    <i class="size-3.5" data-lucide="outdent"></i>
                </button>
                <button class="grid size-8 place-items-center rounded border border-transparent text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-btn="indent" title="Tăng lề">
                    <i class="size-3.5" data-lucide="indent"></i>
                </button>
            @endunless

            <!-- Clear formatting -->
            <button class="grid size-8 place-items-center rounded border border-transparent text-xs font-bold text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-btn="removeFormat" title="Xóa định dạng">
                T<sub>x</sub>
            </button>

            <span class="mx-0.5 h-4 w-px bg-slate-300"></span>

            <!-- History & Source -->
            <button class="grid size-8 place-items-center rounded border border-transparent text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-btn="undo" title="Hoàn tác (Ctrl+Z)">
                <i class="size-3.5" data-lucide="undo"></i>
            </button>
            <button class="grid size-8 place-items-center rounded border border-transparent text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-btn="redo" title="Làm lại (Ctrl+Y)">
                <i class="size-3.5" data-lucide="redo"></i>
            </button>
            <button class="grid size-8 place-items-center rounded border border-transparent font-mono text-xs font-bold text-slate-700 hover:border-slate-300 hover:bg-white active:bg-slate-200" type="button" data-toolbar-action="source" title="Xem mã nguồn HTML">
                &lt;&gt;
            </button>
        </div>

        <!-- Editable Area -->
        <div class="relative">
            <div
                id="{{ $id }}-editor"
                class="rich-editor-content {{ $heightClass }} p-3 sm:p-4 text-sm leading-6 text-slate-800 outline-none overflow-y-auto"
                contenteditable="true"
                data-editor-content
                data-placeholder="{{ $placeholder }}"
                role="textbox"
                aria-multiline="{{ $singleLine ? 'false' : 'true' }}"
            >{!! $initialValue !!}</div>

            <!-- Raw HTML Source Code Area (hidden by default) -->
            <textarea
                class="hidden rich-editor-source {{ $heightClass }} w-full border-0 p-3 sm:p-4 font-mono text-xs leading-5 text-slate-800 bg-slate-900 text-slate-100 outline-none resize-y"
                data-editor-source
                spellcheck="false"
            ></textarea>
        </div>
    </div>

    <!-- Hidden Input for Form Submission -->
    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        class="sr-only"
        tabindex="-1"
        data-editor-hidden
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
    >{{ $initialValue }}</textarea>

    @if ($hint)
        <p class="text-xs leading-5 text-slate-500">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="flex items-start gap-2 text-sm font-medium text-danger-700" id="{{ $id }}-error">
            <span aria-hidden="true" class="mt-2 size-1.5 shrink-0 rounded-full bg-danger-600"></span>
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
