<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Request; // TAMBAHKAN INI

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'role',
        'avatar',
        'is_active',
        'join_date',
        'notes',
        'last_login_at',
        'last_login_ip',
        'current_login_at',
        'current_login_ip',
        'login_count', // TAMBAHKAN INI
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'join_date' => 'date',
        'last_login_at' => 'datetime',
        'current_login_at' => 'datetime', // TAMBAHKAN INI
    ];

    // ========== RELATIONSHIPS ==========
    
    // Relationship dengan Order sebagai tailor
    public function tailorOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'tailor_id');
    }

    // Relationship dengan Login History (jika ingin tabel terpisah)
    // public function loginHistories(): HasMany
    // {
    //     return $this->hasMany(LoginHistory::class);
    // }

    // ========== FILAMENT ACCESS CONTROL ==========
    
    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, ['admin', 'manager']);
    }

    // ========== LOGIN HISTORY METHODS ==========
    
    /**
     * Update login history saat user login
     */
    public function recordLogin(): void
    {
        $currentIp = Request::ip();
        
        // Simpan data login sebelumnya sebagai last_login
        $this->last_login_at = $this->current_login_at;
        $this->last_login_ip = $this->current_login_ip;
        
        // Update data login saat ini
        $this->current_login_at = now();
        $this->current_login_ip = $currentIp;
        $this->login_count = $this->login_count + 1;
        
        $this->save();
    }

    /**
     * Update login history saat user logout
     */
    public function recordLogout(): void
    {
        // Anda bisa menambahkan logika logout di sini jika diperlukan
        // Misalnya menyimpan waktu logout
    }

    /**
     * Get formatted last login
     */
    public function getFormattedLastLoginAttribute(): string
    {
        if (!$this->last_login_at) {
            return 'Never logged in';
        }
        
        return $this->last_login_at->format('d/m/Y H:i') . 
               ($this->last_login_ip ? ' (IP: ' . $this->last_login_ip . ')' : '');
    }

    /**
     * Get formatted current login
     */
    public function getFormattedCurrentLoginAttribute(): string
    {
        if (!$this->current_login_at) {
            return 'Not currently logged in';
        }
        
        return $this->current_login_at->format('d/m/Y H:i') . 
               ($this->current_login_ip ? ' (IP: ' . $this->current_login_ip . ')' : '');
    }

    /**
     * Check if user is currently logged in
     */
    public function isCurrentlyLoggedIn(): bool
    {
        return !is_null($this->current_login_at) && 
               $this->current_login_at->diffInMinutes(now()) < 30;
    }

    // ========== SCOPES ==========
    
    public function scopeRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope untuk user yang sedang login
    public function scopeCurrentlyLoggedIn($query)
    {
        return $query->whereNotNull('current_login_at')
                     ->where('current_login_at', '>', now()->subMinutes(30));
    }

    // Scope untuk user dengan IP tertentu
    public function scopeWithIp($query, $ip)
    {
        return $query->where('current_login_ip', $ip)
                     ->orWhere('last_login_ip', $ip);
    }

    // Scope khusus untuk tailor
    public function scopeTailors($query)
    {
        return $query->where('role', 'tailor');
    }

    // ========== HELPER METHODS ==========
    
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isTailor(): bool
    {
        return $this->role === 'tailor';
    }

    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    // ========== AVATAR & FORMATTING ==========
    
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }

    public function getFormattedAddress(): string
    {
        if (!$this->address) {
            return 'No address provided';
        }
        return nl2br(e($this->address));
    }

    public function getFormattedNotes(): string
    {
        if (!$this->notes) {
            return 'No notes available';
        }
        return nl2br(e($this->notes));
    }
}