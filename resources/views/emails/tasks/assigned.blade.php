<x-mail::message>
# New Task Assigned: {{ $task->name }}

Hello {{ $assignee->name }},

A new task has been assigned to you: **{{ $task->name }}** in project **{{ $task->project->name }}**.

**Description:**
{{ $task->description }}

**Due Date:** {{ $task->due_date ? $task->due_date->format('Y-m-d') : 'N/A' }}
**Priority:** {{ $task->priority }}

You can view the task details by clicking the button below:

<x-mail::button :url="url('/tasks/' . $task->id)">
View Task
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
