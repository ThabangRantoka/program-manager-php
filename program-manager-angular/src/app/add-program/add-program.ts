import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { ProgramService } from '../program'; // <-- FIXED: Removed '.service'

@Component({
  selector: 'app-add-program',
  standalone: true,
  imports: [FormsModule, CommonModule],
  templateUrl: './add-program.html',
  styleUrl: './add-program.css'
})
export class AddProgram {
  program = {
    name: '',
    duration: 0,
    time_slot: '',
    channel: ''
  };

  message = '';

  constructor(private programService: ProgramService) {}

  onSubmit() {
    this.programService.addProgram(this.program).subscribe({
      next: (response: any) => { // <-- FIXED: Added type
        this.message = 'Program added successfully!';
        this.program = { name: '', duration: 0, time_slot: '', channel: '' };
        
        // Send the signal to refresh the list!
        this.programService.notifyProgramAdded(); 
      },
      error: (error: any) => { // <-- FIXED: Added type
        this.message = 'Error adding program.';
      }
    });
  }
}