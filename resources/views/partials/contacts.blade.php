<section class="panel">
    <div class="panel-header"><div><h2>Hiring contacts</h2><p>Additional people involved in your client's hiring process</p></div><span class="badge neutral">{{ $contacts->count() }} contacts</span></div>
    <div class="contact-list">
    @forelse($contacts as $contact)
        <details class="contact-card">
            <summary><span class="avatar purple">{{ mb_substr($contact->name,0,1) }}</span><span><strong>{{ $contact->name }}</strong><small>{{ $contact->job_title ?: 'Hiring contact' }} · {{ $contact->email }}</small></span><x-icon name="chevron"/></summary>
            <form method="post" action="{{ route('contacts.update',[$record->id,$contact->id]) }}">
                @csrf
                @method('PUT')
                <div class="form-grid compact-grid">
                    <div><label for="contact-name-{{ $contact->id }}">Name</label><input id="contact-name-{{ $contact->id }}" name="name" value="{{ $contact->name }}" required maxlength="255"></div>
                    <div><label for="contact-title-{{ $contact->id }}">Job title</label><input id="contact-title-{{ $contact->id }}" name="job_title" value="{{ $contact->job_title }}" maxlength="255"></div>
                    <div><label for="contact-email-{{ $contact->id }}">Email</label><input id="contact-email-{{ $contact->id }}" type="email" name="email" value="{{ $contact->email }}" required></div>
                    <div><label for="contact-phone-{{ $contact->id }}">Phone</label><input id="contact-phone-{{ $contact->id }}" name="phone" value="{{ $contact->phone }}" maxlength="255"></div>
                </div><button class="button small">Save contact</button>
            </form>
            <form method="post" action="{{ route('contacts.destroy',[$record->id,$contact->id]) }}" data-confirm="Remove this hiring contact?">@csrf @method('DELETE')<button class="button small danger">Remove contact</button></form>
        </details>
    @empty
        <p class="empty-inline">The primary hiring contact is recorded below. Add more contacts as your relationship grows.</p>
    @endforelse
    </div>
    <details class="contact-add"><summary>+ Add hiring contact</summary><form method="post" action="{{ route('contacts.store',$record->id) }}">@csrf<div class="form-grid compact-grid"><div><label for="new-contact-name">Full name *</label><input id="new-contact-name" name="name" required maxlength="255"></div><div><label for="new-contact-title">Job title</label><input id="new-contact-title" name="job_title" maxlength="255"></div><div><label for="new-contact-email">Email *</label><input id="new-contact-email" type="email" name="email" required></div><div><label for="new-contact-phone">Phone</label><input id="new-contact-phone" name="phone" maxlength="255"></div></div><button class="button primary small">Add contact</button></form></details>
</section>
