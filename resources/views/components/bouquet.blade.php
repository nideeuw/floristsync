<svg viewBox="0 0 320 380" {{ $attributes->merge(['class' => 'w-full']) }} aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="fsaVase" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0" stop-color="#FFFFFF" />
            <stop offset="0.55" stop-color="#F7EEE9" />
            <stop offset="1" stop-color="#E3D2CA" />
        </linearGradient>
        <g id="fsaBloom">
            <ellipse cx="0" cy="-22" rx="13" ry="22" />
            <ellipse cx="0" cy="-22" rx="13" ry="22" transform="rotate(60)" />
            <ellipse cx="0" cy="-22" rx="13" ry="22" transform="rotate(120)" />
            <ellipse cx="0" cy="-22" rx="13" ry="22" transform="rotate(180)" />
            <ellipse cx="0" cy="-22" rx="13" ry="22" transform="rotate(240)" />
            <ellipse cx="0" cy="-22" rx="13" ry="22" transform="rotate(300)" />
            <circle r="8" fill="#F4D58D" stroke="none" />
        </g>
        <path id="fsaLeaf" d="M0 0C14-14 38-14 54 0 38 14 14 14 0 0Z" />
    </defs>

    <g class="fs-stems" fill="none" stroke="#7E8C69" stroke-width="4.5" stroke-linecap="round">
        <path pathLength="1" d="M160 270C160 220 160 160 160 105" />
        <path pathLength="1" d="M160 270C142 225 112 190 92 145" />
        <path pathLength="1" d="M160 270C180 225 212 190 232 142" />
        <path pathLength="1" d="M160 270C128 245 90 225 66 200" />
        <path pathLength="1" d="M160 270C192 245 230 225 254 198" />
    </g>
    <g fill="#8FA07C">
        <g class="fs-leaf fs-l1"><use href="#fsaLeaf" transform="translate(158 235) rotate(-140) scale(0.85)" /></g>
        <g class="fs-leaf fs-l2"><use href="#fsaLeaf" transform="translate(162 222) rotate(-35) scale(0.85)" /></g>
        <g class="fs-leaf fs-l3"><use href="#fsaLeaf" transform="translate(120 200) rotate(-165) scale(0.65)" /></g>
        <g class="fs-leaf fs-l4"><use href="#fsaLeaf" transform="translate(205 200) rotate(-15) scale(0.65)" /></g>
    </g>
    <g stroke="#FFFFFF" stroke-opacity="0.5" stroke-width="1.5">
        <g class="fs-bloom fs-b1"><use href="#fsaBloom" fill="#F6B0BB" transform="translate(160 100) scale(1.3)" /></g>
        <g class="fs-bloom fs-b2"><use href="#fsaBloom" fill="#FFFFFF" transform="translate(90 142) scale(1.05)" /></g>
        <g class="fs-bloom fs-b3"><use href="#fsaBloom" fill="#E98FA0" transform="translate(232 138) scale(1.1)" /></g>
        <g class="fs-bloom fs-b4"><use href="#fsaBloom" fill="#F1C8CB" transform="translate(66 198) scale(0.8)" /></g>
        <g class="fs-bloom fs-b5"><use href="#fsaBloom" fill="#FFFFFF" transform="translate(254 196) scale(0.8)" /></g>
    </g>
    <g class="fs-vase">
        <ellipse cx="160" cy="366" rx="70" ry="8" fill="#374030" opacity="0.12" />
        <path d="M124 262C119 305 90 325 102 352C108 366 212 366 218 352C230 325 201 305 196 262Z" fill="url(#fsaVase)" />
        <ellipse cx="160" cy="262" rx="36" ry="8" fill="#E3D2CA" />
    </g>
</svg>