import Quill from 'quill';
import 'quill/dist/quill.snow.css';

/**
 * Wires a Quill editor to a hidden <textarea> for standard form submission.
 * The server independently re-sanitizes whatever HTML this produces
 * (see Post::htmlSanitizer()), so the toolbar here only needs to stay
 * roughly in sync with that allow-list — it isn't the security boundary.
 */
function initPostBodyEditor(container) {
    const hiddenInput = document.getElementById(container.dataset.input);
    const uploadUrl = container.dataset.uploadUrl;
    const form = container.closest('form');

    const quill = new Quill(container, {
        theme: 'snow',
        placeholder: 'Write your post here...',
        modules: {
            toolbar: {
                container: [
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ align: [] }],
                    ['link', 'image'],
                    ['clean'],
                ],
                handlers: {
                    image: () => selectAndUploadImage(quill, uploadUrl),
                },
            },
        },
    });

    if (hiddenInput.value) {
        quill.clipboard.dangerouslyPasteHTML(hiddenInput.value);
    }

    quill.on('text-change', () => {
        hiddenInput.value = quill.root.innerHTML;
    });

    form?.addEventListener('submit', () => {
        hiddenInput.value = quill.root.innerHTML;
    });
}

function selectAndUploadImage(quill, uploadUrl) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,image/webp';

    input.onchange = async () => {
        const file = input.files?.[0];
        if (!file || !uploadUrl) {
            return;
        }

        const range = quill.getSelection(true);
        const placeholderText = 'Uploading image…';
        quill.insertText(range.index, placeholderText, 'italic', true);

        let placeholderRemoved = false;
        const removePlaceholder = () => {
            if (!placeholderRemoved) {
                placeholderRemoved = true;
                quill.deleteText(range.index, placeholderText.length);
            }
        };

        const body = new FormData();
        body.append('image', file);

        try {
            const response = await fetch(uploadUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body,
            });

            if (!response.ok) {
                throw new Error('Upload failed');
            }

            const { url } = await response.json();
            removePlaceholder();
            quill.insertEmbed(range.index, 'image', url, 'user');
            quill.setSelection(range.index + 1, 0, 'user');
        } catch (err) {
            removePlaceholder();
            console.error('Post image upload failed', err);
            window.alert('Sorry, that image could not be uploaded. Please try a different file.');
        }
    };

    input.click();
}

document.querySelectorAll('[data-post-body-editor]').forEach(initPostBodyEditor);
