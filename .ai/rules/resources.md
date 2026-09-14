---
paths:
  - 'app/Filament/Resources/**'
---

# Resources

## A model with a Filament resource must have a policy
Adding a Filament resource for a model means writing `App\Policies\{Model}Policy` in the same change, with all six abilities spelled out: viewAny, view, create, update, delete, deleteAny.

Filament reads an ability a policy does not mention as **allowed** — see `Filament\get_authorization_response()`, which falls through to the gate's before callbacks and then allows. So a resource with no policy, or a policy missing `delete()`, is open to every account that reaches the panel. This was real: Author, Book and Publisher had no `delete()` anywhere, and `AuthorResourceTest`/`BookResourceTest`/`PublisherResourceTest` drove those resources as a **client** account and passed.

`tests/Feature/PolicyCoverageTest.php` enforces it — it walks `Filament::getPanels()`, and a new resource fails it until the policy exists. Do not weaken that test to make a resource pass; write the policy.

Laravel auto-discovers `App\Policies\{Model}Policy`, so no registration is needed — only the name.
