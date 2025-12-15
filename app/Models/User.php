<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Traits\ModelTrait;
use App\Traits\Walletable;
use App\Models\ProviderDetails;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;
    use InteractsWithMedia;
    use HasRoles;
    use Walletable;
    use ModelTrait;

    protected $guarded = ['avatar'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        "created_at",
        "updated_at",
        "deleted_at",
        "email_verified_at",
        "sms",
        "latest_o_t_p_token",
        
    ];

    protected $appends = [
        'avatar',"city_name" , "image"
    ];
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];


    # Getters

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('avatar')
            ->singleFile()
            ->useDisk(env('FILESYSTEM_DISK') ?? 'public');
    }
    public function isActive(): bool
    {
        return $this->is_active ?? 1;
    }

    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => ($this->getFirstMediaUrl('avatar') != '' ? $this->getFirstMediaUrl('avatar') : asset('assets/avatar.png')),
        );
    }

    protected function password(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => (Hash::make($value)),
        );
    }


    # Relations
    public function deviceTokens(): MorphMany
    {
        return $this->morphMany(DeviceToken::class, 'tokenable');
    }
    public function latestOTPToken(): MorphOne
    {
        return $this->morphOne(AuthenticatableOtp::class, 'authenticatable')->latestOfMany();
    }

    public function OTPTokens(): MorphMany
    {
        return $this->morphMany(AuthenticatableOtp::class, 'authenticatable')->whereActive(true)->latest();
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }
    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }


    #Relations 
    public function providerDetails()
    {
        return $this->hasOne(ProviderDetails::class, 'user_id', 'id');
    }

     protected function image(): Attribute
    {
        return Attribute::make(
        get: fn() => $this->getFirstMediaUrl('client-image') ?: asset('storage/9/download.jpg'),
        );
    }

    public function cityName(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->city ? $this->city->name : null,
        );
    }



}
