<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'code',
        'name',
        'description',
        'head_user_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Parent department (for hierarchy).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    /**
     * Child departments.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * All descendants (recursive).
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Department head/manager.
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    /**
     * Users assigned to this department.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_user')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Users with this as primary department.
     */
    public function primaryUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_user')
            ->wherePivot('is_primary', true)
            ->withTimestamps();
    }

    /**
     * Get all ancestor departments.
     */
    public function getAncestors(): \Illuminate\Support\Collection
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Get all descendant department IDs.
     */
    public function getAllDescendantIds(): array
    {
        $ids = [];

        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }

        return $ids;
    }

    /**
     * Get full path (breadcrumb).
     */
    public function getFullPathAttribute(): string
    {
        $path = collect([$this->name]);
        $parent = $this->parent;

        while ($parent) {
            $path->prepend($parent->name);
            $parent = $parent->parent;
        }

        return $path->implode(' > ');
    }

    /**
     * Get depth level in hierarchy.
     */
    public function getDepthAttribute(): int
    {
        $depth = 0;
        $parent = $this->parent;

        while ($parent) {
            $depth++;
            $parent = $parent->parent;
        }

        return $depth;
    }

    /**
     * Check if this department is root.
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if this department has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Check if user is the head of this department.
     */
    public function isHeadedBy(User $user): bool
    {
        return $this->head_user_id === $user->id;
    }

    /**
     * Scope for root departments only.
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope for active departments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope with ordering.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get flat tree structure for dropdowns.
     */
    public static function getFlatTree(?int $excludeId = null): \Illuminate\Support\Collection
    {
        $departments = self::with('parent')
            ->active()
            ->ordered()
            ->get();

        $buildTree = function ($parentId = null, $prefix = '') use (&$buildTree, $departments, $excludeId) {
            $result = collect();

            $children = $departments->where('parent_id', $parentId);

            foreach ($children as $child) {
                if ($excludeId && ($child->id === $excludeId || in_array($child->id, (new self)->find($excludeId)?->getAllDescendantIds() ?? []))) {
                    continue;
                }

                $result->push([
                    'id' => $child->id,
                    'name' => $prefix . $child->name,
                    'code' => $child->code,
                    'depth' => substr_count($prefix, '— '),
                ]);

                $result = $result->merge($buildTree($child->id, $prefix . '— '));
            }

            return $result;
        };

        return $buildTree();
    }
}
