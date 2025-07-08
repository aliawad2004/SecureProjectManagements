<x-mail::message>
# New Comment from {{ $commenter->name }}

A new comment has been added by **{{ $commenter->name }}** on your
{{ $commentable instanceof \App\Models\Project ? 'project' : 'task' }}: **{{ $commentable->name }}**.

---
**Comment:**
{{ $comment->content }}
---

You can view the {{ $commentable instanceof \App\Models\Project ? 'project' : 'task' }} and comment by clicking the button below:

<x-mail::button :url="url('/' . ($commentable instanceof \App\Models\Project ? 'projects' : 'tasks') . '/' . $commentable->id . '#comment-' . $comment->id)">
View {{ $commentable instanceof \App\Models\Project ? 'Project' : 'Task' }}
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
