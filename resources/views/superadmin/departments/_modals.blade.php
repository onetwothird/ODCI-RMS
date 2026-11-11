{{-- Create Department Modal --}}
<div id="createDeptModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Department</h3>
            <button type="button" class="close" onclick="closeModal('createDeptModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('superadmin.departments.store') }}">
            @csrf
            <div class="form-row">
                <div class="form-group">
                    <label for="department_code">Department Code *</label>
                    <input type="text" id="department_code" name="department_code" maxlength="10" required 
                           style="text-transform: uppercase;" placeholder="e.g., ITD">
                    <small style="color: #666;">Max 10 characters, will be converted to uppercase</small>
                    @error('department_code')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="department_name">Department Name *</label>
                    <input type="text" id="department_name" name="department_name" required 
                           placeholder="e.g., Information Technology Department">
                    @error('department_name')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3" 
                          placeholder="Brief description of the department..."></textarea>
                @error('description')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="head_of_department">Head of Department</label>
                <input type="text" id="head_of_department" name="head_of_department" 
                       placeholder="e.g., Dr. John Smith">
                @error('head_of_department')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="contact_email">Contact Email</label>
                    <input type="email" id="contact_email" name="contact_email" 
                           placeholder="department@cvsu.edu.ph">
                    @error('contact_email')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="contact_phone">Contact Phone</label>
                    <input type="tel" id="contact_phone" name="contact_phone" 
                           placeholder="+63-2-1234-5678">
                    @error('contact_phone')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                <button type="button" onclick="closeModal('createDeptModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Department</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Department Modal --}}
<div id="editDeptModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Department</h3>
            <button type="button" class="close" onclick="closeModal('editDeptModal')">&times;</button>
        </div>
        <form method="POST" action="" id="editDeptForm">
            @csrf
            @method('PATCH')
            <input type="hidden" id="edit_department_id" name="department_id">

            <div class="form-row">
                <div class="form-group">
                    <label for="edit_department_code">Department Code *</label>
                    <input type="text" id="edit_department_code" name="department_code" maxlength="10" required 
                           style="text-transform: uppercase;">
                    @error('department_code')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="edit_department_name">Department Name *</label>
                    <input type="text" id="edit_department_name" name="department_name" required>
                    @error('department_name')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="edit_description">Description</label>
                <textarea id="edit_description" name="description" rows="3"></textarea>
                @error('description')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="edit_head_of_department">Head of Department</label>
                <input type="text" id="edit_head_of_department" name="head_of_department">
                @error('head_of_department')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="edit_contact_email">Contact Email</label>
                    <input type="email" id="edit_contact_email" name="contact_email">
                    @error('contact_email')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="edit_contact_phone">Contact Phone</label>
                    <input type="tel" id="edit_contact_phone" name="contact_phone">
                    @error('contact_phone')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-group checkbox">
                <input type="checkbox" id="edit_is_active" name="is_active">
                <label for="edit_is_active">Department Active</label>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                <button type="button" onclick="closeModal('editDeptModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Department</button>
            </div>
        </form>
    </div>
</div>