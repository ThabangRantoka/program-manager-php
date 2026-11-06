import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ProgramService } from '../program'; // <-- FIXED: Removed '.service'

@Component({
  selector: 'app-program-list',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './program-list.html',
  styleUrl: './program-list.css'
})
export class ProgramList implements OnInit {
  programs: any[] = [];

  constructor(private programService: ProgramService) {}

  ngOnInit() {
    this.loadPrograms();
    
    // Listen for the signal to reload the list
    this.programService.programAdded$.subscribe(() => {
      this.loadPrograms();
    });
  }

  loadPrograms() {
    this.programService.getPrograms().subscribe((data: any[]) => { // <-- FIXED: Added type
      this.programs = data;
    });
  }
}