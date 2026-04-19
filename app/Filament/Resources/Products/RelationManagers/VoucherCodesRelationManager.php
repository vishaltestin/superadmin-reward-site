<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema; // ✅ v4 uses Schema instead of Form
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions; // ✅ v4 actions namespace

class VoucherCodesRelationManager extends RelationManager
{
    protected static string $relationship = 'voucherCodes';

    protected static ?string $recordTitleAttribute = 'code';
    
    protected static ?string $title = 'Digital Inventory (Codes)';

    // MAGIC: Only show this table if the product is a Digital Voucher!
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->type === 'digital';
    }

    public function form(Schema $schema): Schema // ✅ changed Form → Schema
    {
        return $schema
            ->schema([
                TextInput::make('code')
                    ->label('Voucher Code / Link')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                    
                TextInput::make('pin')
                    ->label('PIN (Optional)')
                    ->maxLength(255),
                    
                DatePicker::make('expires_at')
                    ->label('Code Expiry Date (Optional)'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Secret Code')
                    ->searchable()
                    ->copyable() // Lets admins click to copy the code
                    ->weight('bold'),
                    
                TextColumn::make('pin')
                    ->label('PIN')
                    ->default('-'),
                    
                IconColumn::make('is_used')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('danger') // Red if used
                    ->falseColor('success'), // Green if available
                    
                TextColumn::make('issuedToUser.email')
                    ->label('Claimed By')
                    ->default('Available')
                    ->searchable(),
                    
                TextColumn::make('expires_at')
                    ->label('Expires On')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_used')
                    ->label('Usage Status')
                    ->trueLabel('Used Codes')
                    ->falseLabel('Available Codes'),
            ])
            ->headerActions([
                Actions\CreateAction::make()->label('Add Single Code'), // ✅ updated namespace
                // Note: Later, we can add an ImportAction here to upload a CSV of 1,000 codes at once!
            ])
            ->actions([
                Actions\EditAction::make() // ✅ updated namespace
                    // Don't let admins edit a code after a user has bought it
                    ->hidden(fn ($record) => $record->is_used),

                Actions\DeleteAction::make() // ✅ updated namespace
                    ->hidden(fn ($record) => $record->is_used),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([ // ✅ updated namespace
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}